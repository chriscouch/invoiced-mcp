<?php

namespace App\Integrations\Adyen;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use App\Core\I18n\PhoneFormatter;
use App\Core\Mailer\Mailer;
use App\Core\Utils\RandomString;
use App\Integrations\Adyen\Exception\FlywireOnboardingException;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Operations\GeneratePricing;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use libphonenumber\PhoneNumberFormat;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

class FlywirePaymentsOnboarding implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly string $environment,
        private readonly string $projectDir,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AdyenClient $adyen,
        private readonly UserContext $userContext,
        private bool $adyenLiveMode,
        private GeneratePricing $generatePricing,
        private Mailer $mailer,
    ) {
    }

    /**
     * Checks if a company is eligible for Flywire Payments (Payfac).
     */
    public function isEligible(Company $company): bool
    {
        // Not available in sandbox accounts
        if ($company->test_mode || 'sandbox' == $this->environment) {
            return false;
        }

        // Must be in a supported country
        if (!in_array($company->country, AdyenConfiguration::getSupportedCounties())) {
            return false;
        }

        // Must have card and/or ACH features
        if (!$company->features->has('card_payments') && !$company->features->has('ach')) {
            return false;
        }

        // Cannot be a Flywire MOR priority account
        if ($company->features->has('flywire_mor_target')) {
            return false;
        }

        // Cannot be ineligible based on an installed product or account marked ineligible
        if ($company->features->has('flywire_payments_ineligible')) {
            return false;
        }

        return true;
    }

    /**
     * Checks if a company is already enrolled in Flywire Payments (MoR or Payfac).
     */
    public function isAlreadyEnrolled(Company $company): bool
    {
        // Check if Flywire MoR is already enabled
        $count = PaymentMethod::where('gateway', FlywireGateway::ID)
            ->where('enabled', true)
            ->count();
        if ($count > 0) {
            return true;
        }

        // Check if already has an Adyen account that completed the first step of onboarding
        $adyenAccount = AdyenAccount::queryWithTenant($company)->oneOrNull();

        return !$this->needsStartPage($adyenAccount);
    }

    /**
     * Checks if the onboarding start page should be shown.
     */
    public function needsStartPage(?AdyenAccount $adyenAccount): bool
    {
        if (!$adyenAccount) {
            return true;
        }

        return !$adyenAccount->industry_code || !$adyenAccount->terms_of_service_acceptance_ip || !$adyenAccount->tenant()->phone;
    }

    private function getIndustryCodes(): array
    {
        $industryData = json_decode((string) file_get_contents($this->projectDir.'/config/adyenIndustryCodes.json'), true);
        $codes = $industryData['no_approval_needed'];
        usort($codes, fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $codes;
    }

    public function makeForm(AdyenAccount $adyenAccount): FormInterface
    {
        $industryChoices = ['' => 'Please choose'];
        foreach ($this->getIndustryCodes() as $row) {
            $industryChoices[$row['description']] = $row['code'];
        }

        $company = $adyenAccount->tenant();
        $industryCode = $adyenAccount->industry_code;
        if (!$industryCode && $industry = $company->industry) {
            $industryCode = AdyenConfiguration::getIndustryCode($industry);
        }

        $builder = $this->formFactory->createBuilder(
            FormType::class,
            [
                'industryCode' => $industryCode,
            ],
            [
                'translation_domain' => 'general',
            ])
            ->add('industryCode', ChoiceType::class, [
                'label' => 'Industry Category',
                'choices' => $industryChoices,
                'constraints' => new NotBlank(),
            ])
            ->add('agree', CheckboxType::class, [
                'label' => 'I agree to the LINK',
                'mapped' => false,
                'constraints' => new IsTrue(),
            ]);

        if (!$company->phone) {
            $builder
                ->add('phone', TextType::class, [
                    'label' => 'Phone Number',
                    'constraints' => new NotBlank(),
                ]);
        }

        return $builder->getForm();
    }

    public function getOnboardingStartUrl(Company $company): string
    {
        $onboardingUrl = $this->urlGenerator->generate(
            'flywire_onboarding_start',
            ['companyId' => $company->identifier],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // This is only needed in dev
        return str_replace('invoiced.localhost/', 'invoiced.localhost:1234/', $onboardingUrl);
    }

    public function getOnboardingAdyenRedirectUrl(Company $company): string
    {
        $onboardingUrl = $this->urlGenerator->generate(
            'flywire_onboarding_redirect',
            ['companyId' => $company->identifier],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // This is only needed in dev
        return str_replace('invoiced.localhost/', 'invoiced.localhost:1234/', $onboardingUrl);
    }

    public function canOnboard(Company $company): bool
    {
        if (!$user = $this->userContext->get()) {
            return false;
        }
        $member = Member::getForUser($user);
        if (!$member || !$company->memberCanEdit($member)) {
            return false;
        }

        return $this->isEligible($company);
    }

    /**
     * @throws FlywireOnboardingException
     */
    public function setupAccountForOnboarding(AdyenAccount $adyenAccount): AdyenAccount
    {
        $company = $adyenAccount->tenant();

        // Adyen requires international formatting for the phone number
        $phone = $company->phone ?: '';
        if ($phone) {
            $phone = PhoneFormatter::format($phone, $company->country, PhoneNumberFormat::INTERNATIONAL);
        }

        // Set up a reference to use to identify all the resources created
        if (!$adyenAccount->reference) {
            $adyenAccount->reference = $company->id.'-'.RandomString::generate(10);
            $adyenAccount->saveOrFail();
        }

        // Create legal entity
        if (!$adyenAccount->legal_entity_id) {
            $this->createLegalEntity($company, $adyenAccount, $phone);
        }

        // Create account holder
        if (!$adyenAccount->account_holder_id) {
            $this->createAccountHolder($adyenAccount, $company);
        }

        // Create balance accounts
        $balanceAccountDescription = 'Default Account';
        if (!$adyenAccount->balance_account_id) {
            $this->createBalanceAccount($adyenAccount, $company, $balanceAccountDescription);
        }

        // Create business line
        if (!$adyenAccount->business_line_id) {
            $this->createBusinessLine($adyenAccount, $company);
        }

        // Create Store
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $company->country);
        if (!$adyenAccount->store_id) {
            $this->createStore($adyenAccount, $company, $adyenMerchantAccount, $phone, $balanceAccountDescription);
        }

        // Add payment methods to store
        $this->addPaymentMethodsToStore($adyenAccount, $company, $adyenMerchantAccount);

        // Send onboarding started message
        $this->sendOnboardingStarted($adyenAccount);

        return $adyenAccount;
    }

    /**
     * @throws FlywireOnboardingException
     */
    private function createLegalEntity(Company $company, AdyenAccount $adyenAccount, string $phone): void
    {
        $address = [
            'country' => $company->country,
            'city' => $company->city,
            'postalCode' => $company->postal_code,
            'street' => $company->address1,
            'street2' => $company->address2,
        ];

        // If you specify the state or province, you must also send city, postalCode, and street.
        if (in_array($company->country, ['US', 'CA']) && $state = $company->state) {
            $address['stateOrProvince'] = $state;
        }

        $input = [
            'type' => 'organization',
            'reference' => $adyenAccount->reference,
            'organization' => [
                'description' => 'Company # '.$company->id,
                'legalName' => $company->name,
                'email' => $company->email,
                'phone' => $phone,
                'registeredAddress' => $address,
            ],
        ];

        if ($company->nickname) {
            $input['organization']['doingBusinessAs'] = $company->nickname;
        }

        try {
            $legalEntity = $this->adyen->createLegalEntity($input);
        } catch (IntegrationApiException $e) {
            throw new FlywireOnboardingException('Could not create legal entity: '.$e->getMessage());
        }

        $adyenAccount->legal_entity_id = $legalEntity['id'];
        $adyenAccount->saveOrFail();
    }

    /**
     * @throws FlywireOnboardingException
     */
    private function createAccountHolder(AdyenAccount $adyenAccount, Company $company): void
    {
        try {
            $accountHolder = $this->adyen->createAccountHolder([
                'legalEntityId' => $adyenAccount->legal_entity_id,
                'description' => 'Company # '.$company->id,
                'reference' => $adyenAccount->reference,
                'timeZone' => $company->time_zone,
            ]);
        } catch (IntegrationApiException $e) {
            throw new FlywireOnboardingException('Could not create account holder: '.$e->getMessage());
        }

        $adyenAccount->account_holder_id = $accountHolder['id'];
        $adyenAccount->saveOrFail();
    }

    /**
     * @throws FlywireOnboardingException
     */
    private function createBalanceAccount(AdyenAccount $adyenAccount, Company $company, string $balanceAccountDescription): void
    {
        try {
            $balanceAccount = $this->adyen->createBalanceAccount([
                'accountHolderId' => $adyenAccount->account_holder_id,
                'defaultCurrencyCode' => strtoupper($company->currency),
                'description' => $balanceAccountDescription,
                'reference' => $adyenAccount->reference,
                'timeZone' => $company->time_zone,
            ]);
        } catch (IntegrationApiException $e) {
            throw new FlywireOnboardingException('Could not create balance account: '.$e->getMessage());
        }

        $adyenAccount->balance_account_id = $balanceAccount['id'];
        $adyenAccount->saveOrFail();
    }

    /**
     * @throws FlywireOnboardingException
     */
    private function createBusinessLine(AdyenAccount $adyenAccount, Company $company): void
    {
        try {
            $businessLine = $this->adyen->createBusinessLine([
                'legalEntityId' => $adyenAccount->legal_entity_id,
                'industryCode' => $adyenAccount->industry_code,
                'service' => 'paymentProcessing',
                'salesChannels' => ['eCommerce'],
                'webData' => [
                    ['webAddress' => $company->url],
                ],
            ]);
        } catch (IntegrationApiException $e) {
            throw new FlywireOnboardingException('Could not create business line: '.$e->getMessage());
        }

        $adyenAccount->business_line_id = $businessLine['id'];
        $adyenAccount->saveOrFail();
    }

    /**
     * @throws FlywireOnboardingException
     */
    private function createStore(AdyenAccount $adyenAccount, Company $company, string $adyenMerchantAccount, string $phone, string $balanceAccountDescription): void
    {
        try {
            $zip = $company->postal_code ?? '';
            if ('US' === $company->country && strlen($zip) > 5) {
                $zip = explode('-', $zip)[0];
            }

            if (!isset($adyenAccount->statement_descriptor)) {
                $adyenAccount->statement_descriptor = substr($company->getDisplayName(), 0, 22);
                $adyenAccount->saveOrFail();
            }

            $store = $this->adyen->createStore([
                'address' => [
                    'line1' => $company->address1,
                    'line2' => $company->address2,
                    'city' => $company->city,
                    'stateOrProvince' => $company->state,
                    'postalCode' => $zip,
                    'country' => $company->country,
                ],
                'businessLineIds' => [$adyenAccount->business_line_id],
                'splitConfiguration' => [
                    'balanceAccountId' => $adyenAccount->balance_account_id,
                    'splitConfigurationId' => $this->getSplitConfiguration($adyenAccount),
                ],
                'description' => $company->id.' - Main Store',
                'merchantId' => $adyenMerchantAccount,
                'reference' => $adyenAccount->reference,
                'phoneNumber' => $phone,
                'shopperStatement' => $adyenAccount->getStatementDescriptor(),
            ]);
        } catch (IntegrationApiException $e) {
            throw new FlywireOnboardingException('Could not create store: '.$e->getMessage());
        }

        $adyenAccount->store_id = $store['id'];
        $adyenAccount->saveOrFail();

        $this->createMerchantAccount((string) $adyenAccount->balance_account_id, (string) $adyenAccount->reference, $store['id'], $balanceAccountDescription);
    }

    /**
     * @throws FlywireOnboardingException
     */
    private function getSplitConfiguration(AdyenAccount $adyenAccount): string
    {
        // Check if pricing is already assigned
        if ($splitConfigurationId = $adyenAccount->pricing_configuration?->split_configuration_id) {
            return $splitConfigurationId;
        }

        // Set the pricing on the merchant
        try {
            $company = $adyenAccount->tenant();
            $parameters = AdyenConfiguration::getStandardPricing($this->adyenLiveMode, (string) $company->country, $company->currency);
            $pricingConfiguration = $this->generatePricing->setPricingOnMerchant($adyenAccount, $parameters);
        } catch (IntegrationApiException $e) {
            throw new FlywireOnboardingException('Could not retrieve pricing: '.$e->getMessage());
        }

        return (string) $pricingConfiguration->split_configuration_id;
    }

    private function createMerchantAccount(string $balanceAccountId, string $storeReference, string $storeId, string $balanceAccountDescription): void
    {
        $merchantAccount = new MerchantAccount();
        $merchantAccount->gateway = AdyenGateway::ID;
        $merchantAccount->name = $balanceAccountDescription;
        $merchantAccount->gateway_id = $storeReference;
        $merchantAccount->credentials = (object) [
            'balance_account' => $balanceAccountId,
            'store' => $storeId,
        ];
        $merchantAccount->saveOrFail();
    }

    private function addPaymentMethodsToStore(AdyenAccount $adyenAccount, Company $company, string $adyenMerchantAccount): void
    {
        // See the full menu at https://docs.adyen.com/development-resources/paymentmethodvariant/#management-api
        $methods = ['amex', 'cup', 'discover', 'jcb', 'mc', 'visa', 'applepay'];
        if ('US' == $company->country) {
            $methods[] = 'ach';
        } else {
            $methods[] = 'maestro';
        }

        try {
            $paymentMethods = $this->adyen->getPaymentMethodSettings($adyenMerchantAccount, [
                'pageSize' => 100,
                'businessLineId' => $adyenAccount->business_line_id,
                'storeId' => $adyenAccount->store_id,
            ]);
        } catch (IntegrationApiException $e) {
            $this->logger->error('Could not retrieve Adyen payment method settings', ['exception' => $e]);

            return;
        }

        foreach ($methods as $method) {
            $exists = false;
            foreach ($paymentMethods['data'] as $paymentMethod) {
                if ($paymentMethod['type'] == $method) {
                    $exists = true;
                }
            }

            if ($exists) {
                continue;
            }

            $params = [
                'type' => $method,
                'businessLineId' => $adyenAccount->business_line_id,
                'storeIds' => [$adyenAccount->store_id],
            ];

            if ('amex' == $method) {
                $currencies = [strtoupper($company->currency)];

                if ($company->country === 'CA') {
                    if (!in_array('CAD', $currencies)) {
                        $currencies[] = 'CAD';
                    }
                    if (!in_array('USD', $currencies)) {
                        $currencies[] = 'USD';
                    }
                }

                $params['currencies'] = $currencies;

                $params['amex'] = [
                    'serviceLevel' => 'noContract',
                ];
            } elseif ('applepay' == $method) {
                $url = str_replace('http://', 'https://', $company->url); // only needed for dev
                $params['applePay'] = [
                    'domains' => [$url],
                ];
            }

            try {
                $this->adyen->createPaymentMethodSetting($adyenMerchantAccount, $params);
            } catch (IntegrationApiException $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'Payment method has been already requested') || str_contains($msg, 'Draft request already exists')) {
                    continue;
                }

                $this->logger->error('Could not enable payment method on Adyen store', ['exception' => $e]);
            }
        }
    }

    /**
     * Activates an Adyen account.
     */
    public function activateAccount(AdyenAccount $adyenAccount): void
    {
        // Only run activation once
        if ($adyenAccount->activated_at) {
            return;
        }

        // Enable payments
        $this->updatePaymentMethodEnabled(true);

        // Mark the account as activated
        $adyenAccount->activated_at = CarbonImmutable::now();
        $adyenAccount->saveOrFail();

        // Send the "Account Activated" lifecycle email
        $company = $adyenAccount->tenant();
        $this->mailer->sendToAdministrators(
            $company,
            [
                'subject' => '✅ You’re Live! Flywire Payments Activated on Invoiced',
                'reply_to_email' => 'support@invoiced.com',
                'reply_to_name' => 'Invoiced Support',
            ],
            'flywire-payments-account-activated',
            [
                'name' => $company->name,
            ],
        );
    }

    public function disablePayments(): void
    {
        $this->updatePaymentMethodEnabled(false);
    }

    private function updatePaymentMethodEnabled(bool $enabled): void
    {
        $merchantAccounts = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->sort('id ASC')
            ->first();

        if (!$merchantAccounts) {
            return;
        }

        // Enable card payments
        $merchantAccount = $merchantAccounts[0];
        $this->updatePaymentMethod($merchantAccount, $enabled, PaymentMethod::CREDIT_CARD);

        // Enable ACH Direct Debit for US accounts
        if ('US' === $merchantAccount->tenant()->country) {
            $this->updatePaymentMethod($merchantAccount, $enabled, PaymentMethod::ACH);
        }
    }

    private function updatePaymentMethod(MerchantAccount $adyenMerchantAccount, bool $enabled, string $method): void
    {
        $paymentMethod = PaymentMethod::instance($adyenMerchantAccount->tenant(), $method);
        $merchantAccount = $paymentMethod->merchantAccount();

        if (AdyenGateway::ID !== $merchantAccount?->gateway) {
            // we do not modify existing payment method while use can't use Adyen
            // until Adyen review process is completed
            if (!$enabled && $paymentMethod->enabled) {
                return;
            }
            $paymentMethod->setMerchantAccount($adyenMerchantAccount);
        }

        $paymentMethod->enabled = $enabled;
        $paymentMethod->saveOrFail();
    }

    private function sendOnboardingStarted(AdyenAccount $adyenAccount): void
    {
        if ($adyenAccount->onboarding_started_at) {
            return;
        }

        // Mark the onboarding start time
        $adyenAccount->onboarding_started_at = CarbonImmutable::now();
        $adyenAccount->saveOrFail();

        // Send the "Onboarding Started" lifecycle email
        $company = $adyenAccount->tenant();
        $this->mailer->sendToAdministrators(
            $company,
            [
                'subject' => '🎉 Welcome to Flywire Payments',
                'reply_to_email' => 'support@invoiced.com',
                'reply_to_name' => 'Invoiced Support',
            ],
            'flywire-payments-onboarding-started',
            [
                'name' => $company->name,
            ],
        );
    }
}
