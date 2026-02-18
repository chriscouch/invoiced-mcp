<?php

namespace App\Core\Billing\Action;

use App\Companies\Libs\NewCompanySignUp;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\ValueObjects\EntitlementsChangeset;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Libs\UserRegistration;
use App\Core\Authentication\LoginStrategy\UsernamePasswordLoginStrategy;
use App\Core\Authentication\Models\User;
use App\Core\Billing\BillingSystem\BillingSystemFactory;
use App\Core\Billing\BillingSystem\InvoicedBillingSystem;
use App\Core\Billing\BillingSystem\ResellerBillingSystem;
use App\Core\Billing\Enums\BillingPaymentTerms;
use App\Core\Billing\Enums\PurchasePageReason;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\PurchasePageContext;
use App\Core\Billing\ValueObjects\BillingOneTimeItem;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\I18n\Countries;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\RandomString;
use Carbon\CarbonImmutable;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

class PurchasePageAction implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private CreateOrUpdateCustomerAction $createOrUpdateCustomer,
        private SetDefaultPaymentMethodAction $setDefaultPaymentMethod,
        private CreateOrUpdateSubscriptionAction $subscriptionAction,
        private FormFactoryInterface $formFactory,
        private UserContext $userContext,
        private UserRegistration $userRegistration,
        private NewCompanySignUp $newCompanySignUp,
        private UsernamePasswordLoginStrategy $loginStrategy,
        private BillingSystemFactory $billingSystemFactory,
        private string $environment,
        private LocalizedPricingAdjustment $pricingAdjuster,
    ) {
    }

    public function makeForm(PurchasePageContext $pageContext): FormInterface
    {
        $builder = $this->formFactory->createBuilder(
            FormType::class,
            [
                'company' => $pageContext->billing_profile->name,
            ],
            [
                'translation_domain' => 'general',
            ])
            ->add('company', TextType::class, [
                'required' => true,
            ])
            ->add('person', TextType::class, [
                'label' => 'Your Name',
                'required' => true,
                'attr' => [
                    'class' => 'cc-name',
                ],
            ])
            ->add('email', EmailType::class, [
                'required' => true,
            ])
            ->add('address1', TextType::class, [
                'label' => 'labels.address_line1',
                'required' => true,
                'attr' => [
                    'class' => 'cc-address1',
                ],
            ])
            ->add('address2', TextType::class, [
                'label' => 'labels.address_line2',
                'attr' => [
                    'class' => 'cc-address2',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'labels.address_city',
                'required' => true,
                'attr' => [
                    'class' => 'cc-city',
                ],
            ])
            ->add('postal_code', TextType::class, [
                'label' => 'labels.address_postal_code',
                'attr' => [
                    'class' => 'cc-postal-code',
                ],
            ])
            ->add('agree', CheckboxType::class, [
                'label' => 'I agree to the LINK',
                'mapped' => false,
                'constraints' => new IsTrue(),
            ]);

        // State
        $countries = new Countries();
        $country = $countries->get($pageContext->country);
        if (isset($country['states'])) {
            $choices = [];
            foreach ($country['states'] as $state) {
                $choices[$state['name']] = $state['code'];
            }

            $builder
                ->add('state', ChoiceType::class, [
                    'label' => 'labels.address_state',
                    'choices' => $choices,
                    'attr' => [
                        'class' => 'cc-state',
                    ],
                ]);
        } else {
            $builder
                ->add('state', TextType::class, [
                    'label' => 'labels.address_state',
                ]);
        }

        // Invoiced token for AutoPay forms
        if (BillingPaymentTerms::AutoPay == $pageContext->payment_terms) {
            $builder
                ->add('invoiced_token', HiddenType::class, [
                    'constraints' => new NotBlank(),
                ]);
        }

        return $builder->getForm();
    }

    /**
     * Handles a purchase page submission.
     *
     * @throws BillingException
     */
    public function handle(Request $request, FormInterface $form, PurchasePageContext $pageContext): void
    {
        $data = $form->getData();
        $billingProfile = $pageContext->billing_profile;

        // Automatically upgrade Stripe to Invoiced
        $billingSystemId = $billingProfile->billing_system;
        if (!$billingSystemId || !in_array($billingSystemId, [InvoicedBillingSystem::ID, ResellerBillingSystem::ID])) {
            $billingSystemId = InvoicedBillingSystem::ID;
        }

        // Create or update the customer on the billing system
        $params = [
            'company' => $data['company'],
            'person' => $data['person'],
            'email' => $data['email'],
            'address1' => $data['address1'],
            'address2' => $data['address2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
            'country' => $pageContext->country,
            'sales_rep' => $pageContext->sales_rep,
        ];

        if (BillingPaymentTerms::AutoPay == $pageContext->payment_terms) {
            $params['autopay'] = true;
        } elseif (BillingPaymentTerms::Net30 == $pageContext->payment_terms) {
            $params['autopay'] = false;
            $params['payment_terms'] = 'NET 30';
        }

        $this->createOrUpdateCustomer->perform($billingProfile, $billingSystemId, $params);

        // Set the default payment method if on AutoPay
        if (BillingPaymentTerms::AutoPay == $pageContext->payment_terms) {
            $this->setDefaultPaymentMethod->set($billingProfile, $data['invoiced_token']);
        }

        // Determine localized adjustment
        $localizedAdjustment = 0;
        if ($pageContext->localized_pricing) {
            $localizedAdjustment = $this->pricingAdjuster->getLocalizedAdjustment($pageContext->country);
        }

        // Bill the activation fee
        $this->addActivationFee($pageContext, $localizedAdjustment);

        // Perform the purchase for the given page reason
        $reason = $pageContext->reason;
        if (PurchasePageReason::NewCompany == $reason) {
            $this->handleNewCompany($pageContext, $request, $data, $localizedAdjustment);
        } else {
            $this->handleActivation($pageContext, $localizedAdjustment);
        }

        // Mark page as complete
        $this->markPageComplete($pageContext, $request, $data);
    }

    /**
     * @throws BillingException
     */
    private function addActivationFee(PurchasePageContext $pageContext, float $localizedAdjustment): void
    {
        $activationFee = Money::fromDecimal('usd', $pageContext->activation_fee ?? 0);
        $activationFee = $this->pricingAdjuster->applyAdjustment($activationFee, $localizedAdjustment);
        if (!$activationFee->isPositive()) {
            return;
        }

        $item = new BillingOneTimeItem(
            price: $activationFee,
            description: 'Activation Fee',
            itemId: 'activation-fee',
        );
        $billingSystem = $this->billingSystemFactory->getForBillingProfile($pageContext->billing_profile);
        $billingSystem->billLineItem($pageContext->billing_profile, $item, false);
    }

    /**
     * @throws BillingException
     */
    private function handleNewCompany(PurchasePageContext $pageContext, Request $request, array $data, float $localizedAdjustment): void
    {
        // Create a company
        $company = $this->createTenant($pageContext, $data, $request);

        // Activate it with the new purchase
        $this->applyPurchase($company, $pageContext, $localizedAdjustment);
    }

    /**
     * @throws BillingException
     */
    private function handleActivation(PurchasePageContext $pageContext, float $localizedAdjustment): void
    {
        $company = $pageContext->tenant;
        if (!$company) {
            throw new BillingException('Missing company');
        }

        $this->applyPurchase($company, $pageContext, $localizedAdjustment);
    }

    /**
     * This function handles the creation process
     * for new tenants and users after a purchase.
     *
     * @throws BillingException
     */
    public function createTenant(PurchasePageContext $pageContext, array $data, Request $request): Company
    {
        $user = $this->userContext->get();

        // If there is not an already signed in user, then
        // check for a user with the email address on the purchase.
        if (!$user) {
            $user = User::where('email', $data['email'])->oneOrNull();
        }

        $tempPassword = RandomString::generate(32, RandomString::CHAR_ALNUM).'aB1#';
        $createdUser = false;
        if (!$user) {
            // create the user if it doesn't yet exist
            $name = $data['person'];
            $nameParts = explode(' ', $name);
            $userParams = [
                'first_name' => $nameParts[0],
                'last_name' => implode(' ', array_slice($nameParts, 1)) ?: 'Admin',
                'email' => $data['email'],
                // The password will be provided by the user after verifying their email address.
                // Temporary password, defined above, is a random valid (secure) password for our system.
                'password' => $tempPassword,
                'has_password' => false,
                'ip' => (string) $request->getClientIp(),
            ];

            try {
                $user = $this->userRegistration->registerUser($userParams, true, true);
                $createdUser = true;
            } catch (Exception $e) {
                $this->logger->error('Could not complete sign up; user registration failed', ['exception' => $e]);

                throw new BillingException('User registration failed');
            }
        }

        // create the company
        $companyParams = [
            'name' => $data['company'],
            'country' => $pageContext->country,
            'email' => $data['email'],
            'creator_id' => $user->id(),
            'trial_ends' => null,
            'billing_profile' => $pageContext->billing_profile,
        ];

        if ('sandbox' == $this->environment) {
            $companyParams['test_mode'] = true;
        }

        try {
            // do not apply the purchase page changeset yet because it will be added by billing action
            $changeset = new EntitlementsChangeset(features: ['needs_onboarding' => true]);
            $company = $this->newCompanySignUp->create($companyParams, $changeset);
        } catch (Exception $e) {
            $this->logger->error('Company creation failed in purchase page', ['exception' => $e]);

            throw new BillingException('Company creation failed');
        }

        if ($createdUser) {
            // if a new user was created, we can sign them in
            $this->loginStrategy->login($request, $data['email'], $tempPassword, true);

            // create an email verification token for the company
            $companyEmail = CompanyEmailAddress::queryWithTenant($company)
                ->where('email', $company->email)
                ->oneOrNull();

            if (!$companyEmail) {
                $companyEmail = new CompanyEmailAddress();
                $companyEmail->tenant_id = $company->id;
                $companyEmail->email = (string) $company->email;
                $companyEmail->token = RandomString::generate(24, RandomString::CHAR_ALNUM);
                $companyEmail->saveOrFail();
            }
        }

        // Set the newly created company on the page context but do not save yet
        $pageContext->tenant = $company;

        return $company;
    }

    /**
     * @throws BillingException
     */
    private function applyPurchase(Company $company, PurchasePageContext $pageContext, float $localizedAdjustment): void
    {
        // Apply adjustment to all pricing in changeset
        $changesetJson = $pageContext->changeset;
        foreach ($changesetJson->productPrices as &$productPrice) {
            $unitPrice = new Money('usd', $productPrice->price);
            $productPrice->price = $this->pricingAdjuster->applyAdjustment($unitPrice, $localizedAdjustment)->amount;
        }
        foreach ($changesetJson->usagePricing as &$usagePrice) {
            $unitPrice = new Money('usd', $usagePrice->unit_price);
            $usagePrice->unit_price = $this->pricingAdjuster->applyAdjustment($unitPrice, $localizedAdjustment)->amount;
        }

        $changeset = EntitlementsChangeset::fromJson($changesetJson);
        if (!$changeset->billingInterval) {
            throw new BillingException('Missing billing interval');
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        // Execute the subscription in the billing system
        $this->subscriptionAction->perform($company, $changeset->billingInterval, $changeset);

        // Remove the not_activated feature flag and transactions per day quota
        // that are installed on free and trial accounts
        $company->features->remove('not_activated');
        $company->quota->remove(QuotaType::TransactionsPerDay);
    }

    private function markPageComplete(PurchasePageContext $pageContext, Request $request, array $data): void
    {
        $pageContext->completed_at = CarbonImmutable::now();
        $pageContext->completed_by_ip = $request->getClientIp();
        $pageContext->completed_by_name = $data['person'];
        $pageContext->saveOrFail();
    }
}
