<?php

namespace App\EntryPoint\Controller;

use App\Companies\Libs\CsadminCompanyCreator;
use App\Companies\Libs\FlywirePaymentsCustomerServiceProfile;
use App\Companies\Libs\MarkCompanyFraudulent;
use App\Companies\Libs\UserCustomerServiceProfile;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyAddress;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Models\CompanyPhoneNumber;
use App\Companies\Models\CompanyTaxId;
use App\Core\Authentication\Libs\ResetPassword;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\User;
use App\Core\Billing\Action\CancelSubscriptionAction;
use App\Core\Billing\Action\CreateOrUpdateCustomerAction;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\InvoicedBillingSystem;
use App\Core\Billing\Disputes\DisputeEvidenceGenerator;
use App\Core\Billing\Disputes\StripeDisputeHandler;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\ProductPricingPlan;
use App\Core\Entitlements\Exception\InstallProductException;
use App\Core\Entitlements\Models\Product;
use App\Core\Entitlements\ProductInstaller;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\EntryPoint\QueueJob\BasicExportJob;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Operations\GeneratePricing;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Libs\IntegrationFactory;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use Carbon\CarbonImmutable;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[Route(schemes: '%app.protocol%', host: '%app.domain%')]
class CustomerSupportController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private string $csAdminSecret)
    {
    }

    #[Route(path: '/support_tickets', name: 'new_support_ticket', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function createSupportTicket(Request $request, HttpClientInterface $client, string $environment, UserContext $userContext, string $zendeskApiKey, string $csAdminUrl, LoggerInterface $logger): JsonResponse
    {
        // Verify user is signed in
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            throw new NotFoundHttpException();
        }

        // Locate company
        $company = Company::find($request->request->get('company'));
        if (!$company || !$company->isMember($user)) {
            throw new NotFoundHttpException();
        }

        // Create the ticket
        $tags = $request->request->all('tags');
        $parameters = [
            'ticket' => [
                'requester' => [
                    'email' => $request->request->get('email'),
                    'name' => $request->request->get('name'),
                ],
                'recipient' => 'support@invoiced.com',
                'subject' => $request->request->get('subject'),
                'comment' => [
                    'body' => $request->request->get('message'),
                ],
                'custom_fields' => [
                    // Environment
                    ['id' => '1500014839901', 'value' => 'production' == $environment ? 'production' : 'sandbox'],
                    // Tenant ID
                    ['id' => '1500014896422', 'value' => $company->id()],
                    // Category
                    ['id' => '1500014843781', 'value' => $request->request->get('category_tag')],
                    // Company Name
                    ['id' => '1500014936362', 'value' => $company->name],
                ],
                'tags' => $tags,
                'priority' => $company->features->has('enterprise') ? 'high' : 'normal', // Mark enterprise accounts as high priority
            ],
        ];

        try {
            $response = $client->request('POST', 'https://invoicedsupport.zendesk.com/api/v2/tickets', [
                'json' => $parameters,
                'auth_basic' => 'jared@invoiced.com/token:'.$zendeskApiKey,
            ]);

            $result = json_decode($response->getContent());
            $ticketNumber = $result->ticket->id;
            $ticketUrl = $result->ticket->url;

            // Create a private note with more details
            $userUrl = $csAdminUrl.'/users/'.$user->id();
            $companyUrl = $csAdminUrl.'/companies/'.$company->id();
            $note = '';
            $note .= "User: {$user->name(true)} (# {$user->id()})\n";
            $note .= "$userUrl\n";
            $note .= "Company: {$company->name} (# {$company->id()})\n";
            $note .= "$companyUrl\n";
            $note .= "Browser: {$request->headers->get('User-Agent')}\n";
            $note .= "Current Page: {$request->request->get('current_page')}\n";
            $client->request('PUT', $ticketUrl, [
                'json' => [
                    'ticket' => [
                        'comment' => [
                            'public' => false,
                            'body' => $note,
                        ],
                    ],
                ],
                'auth_basic' => 'jared@invoiced.com/token:'.$zendeskApiKey,
            ]);

            return new JsonResponse([
                'ticket_number' => $ticketNumber,
            ]);
        } catch (ExceptionInterface $e) {
            $content = $e instanceof ClientException ? $e->getResponse()->getContent(false) : null;
            $logger->error('Could not create support ticket', [
                'exception' => $e,
                'content' => $content,
            ]);

            return new JsonResponse([
                'message' => 'There was an error creating your support ticket. Please contact support@invoiced.com for assistance',
            ], 400);
        }
    }

    #[Route(path: '/support_ticket_attachments', name: 'upload_support_ticket_attachments', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function uploadSupportTicketAttachments(Request $request, HttpClientInterface $client, UserContext $userContext, string $zendeskApiKey, string $projectDir, LoggerInterface $logger): Response
    {
        // Verify user is signed in
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            throw new NotFoundHttpException();
        }

        // Check if file attachments were provided
        if (0 == count($request->files)) {
            return new Response('', 204, [
                'X-Frame-Options' => 'ALLOW', // required to keep user on current page
            ]);
        }

        // Upload the file attachments
        try {
            $uploadTokens = [];
            foreach ($request->files->all() as $filesArray) {
                // This will be an array of objects because our form uses multiple="multiple"
                /** @var UploadedFile $uploadedFile */
                foreach ($filesArray as $uploadedFile) {
                    $tempDir = $projectDir.'/var/uploads/';
                    if (!is_dir($tempDir)) {
                        @mkdir($tempDir, 0774);
                    }
                    $file = $uploadedFile->move($tempDir, uniqid());
                    $response = $client->request('POST', 'https://invoicedsupport.zendesk.com/api/v2/uploads.json', [
                        'auth_basic' => 'jared@invoiced.com/token:'.$zendeskApiKey,
                        'headers' => [
                            'Content-Type' => 'application/binary',
                        ],
                        'query' => ['filename' => $uploadedFile->getClientOriginalName()],
                        'body' => fopen($file->getPathname(), 'r'),
                    ]);
                    $result = json_decode($response->getContent());
                    $uploadTokens[] = $result->upload->token;
                }
            }

            if (count($uploadTokens) > 0) {
                $ticketNumber = $request->request->get('ticket_number');
                $client->request('PUT', 'https://invoicedsupport.zendesk.com/api/v2/tickets/'.$ticketNumber, [
                    'auth_basic' => 'jared@invoiced.com/token:'.$zendeskApiKey,
                    'json' => [
                        'ticket' => [
                            'comment' => [
                                'public' => false,
                                'body' => 'Attached files',
                                'uploads' => $uploadTokens,
                            ],
                        ],
                    ],
                ]);
            }

            return new Response('', 204, [
                'X-Frame-Options' => 'ALLOW', // required to keep user on current page
            ]);
        } catch (ExceptionInterface $e) {
            $content = $e instanceof ClientException ? $e->getResponse()->getContent(false) : null;
            $logger->error('Could not upload support file attachment', [
                'exception' => $e,
                'content' => $content,
            ]);

            return new JsonResponse([
                'message' => 'There was an error uploading your file attachment. Please contact support@invoiced.com for assistance',
            ], 400);
        }
    }

    #[Route(path: '/users/zendeskProfile', name: 'zendesk_profile', methods: ['GET'])]
    public function getZendeskProfile(Request $request, string $zendeskProfileKey, UserCustomerServiceProfile $userProfile): JsonResponse
    {
        $incomingToken = $request->query->get('api_token').'';
        if (!$zendeskProfileKey || !hash_equals($zendeskProfileKey, $incomingToken)) {
            throw new NotFoundHttpException();
        }

        $email = $request->query->get('email');
        $user = User::where('email', $email)->oneOrNull();

        if (!$user instanceof User) {
            return new JsonResponse([
                'id' => false,
                'generated_at' => date('Y-m-d H:i:s \Z'),
            ]);
        }

        return new JsonResponse($userProfile->build($user));
    }

    #[Route(path: '/_csadmin/company_details', name: 'csadmin_company_details', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function companyDetails(Request $request, TenantContext $tenant, IntegrationFactory $integrationFactory): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $companyId = $request->request->get('company_id');
        $company = Company::find($companyId);
        if (!$company) {
            return new JsonResponse([
                'error' => 'Company # '.$companyId.' does not exist',
            ]);
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        // Verification Status
        $verificationStatus = [
            'email' => CompanyEmailAddress::getVerificationStatus($company)->value,
            'address' => CompanyAddress::getVerificationStatus($company)->value,
            'phone' => CompanyPhoneNumber::getVerificationStatus($company)->value,
            'tax_id' => CompanyTaxId::getVerificationStatus($company)->value,
        ];

        // Installed integrations
        $integrations = [];
        foreach ($integrationFactory->all($company) as $integrationId => $integration) {
            if ($integration->isConnected()) {
                $type = IntegrationType::fromString($integrationId);
                $integrations[] = [
                    'id' => $type->toString(),
                    'name' => $type->toHumanName(),
                    'database_id' => $type->value,
                ];
            }
        }

        return new JsonResponse([
            'billing' => $company->billing,
            'features' => $company->features->all(),
            'products' => $company->features->allProducts(),
            'verification' => $verificationStatus,
            'integrations' => $integrations,
        ]);
    }

    #[Route(path: '/_csadmin/billing_profile_details', name: 'csadmin_billing_profile_details', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function billingProfileDetails(Request $request, BillingItemFactory $itemFactory): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $billingProfileId = $request->request->get('billing_profile_id');
        $billingProfile = BillingProfile::find($billingProfileId);
        if (!$billingProfile) {
            return new JsonResponse([
                'error' => 'Billing Profile # '.$billingProfileId.' does not exist',
            ]);
        }

        try {
            $items = [];
            $total = Money::zero('usd');

            if ($billingProfile->billing_system && $billingProfile->billing_interval) {
                $billingItems = $itemFactory->generateItems($billingProfile);
                foreach ($billingItems as $billingItem) {
                    $total = $total->add($billingItem->total);
                    $items[] = [
                        'name' => $billingItem->name,
                        'description' => $billingItem->description,
                        'quantity' => $billingItem->quantity,
                        'unit_cost' => $billingItem->price->toDecimal(),
                        'amount' => $billingItem->total->toDecimal(),
                    ];
                }
            }
        } catch (BillingException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ]);
        }

        return new JsonResponse([
            'billing_items' => $items,
            'total' => $total->toDecimal(),
        ]);
    }

    #[Route(path: '/_csadmin/reset_login_counter', name: 'csadmin_reset_login_counter', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function resetLoginCounter(Request $request, RateLimiterFactory $usernameLoginLimiter): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $userId = $request->request->get('user_id');
        $user = User::find($userId);
        if (!$user) {
            return new JsonResponse([
                'error' => 'User # '.$userId.' does not exist',
            ]);
        }

        $limiter = $usernameLoginLimiter->create($user->email);
        $limiter->reset();

        return new JsonResponse(['success' => true]);
    }

    #[Route(path: '/_csadmin/reset_password', name: 'csadmin_reset_password', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function resetPasswordLink(Request $request, ResetPassword $resetPassword): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $userId = $request->request->get('user_id');
        $user = User::find($userId);
        if (!$user) {
            return new JsonResponse([
                'error' => 'User # '.$userId.' does not exist',
            ]);
        }

        $link = $resetPassword->buildLink((int) $user->id(), 'N/A', 'CSAdmin');

        return new JsonResponse([
            'link' => $link->url(),
        ]);
    }

    #[Route(path: '/_csadmin/new_customer', name: 'csadmin_new_customer', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function newCustomer(Request $request, CreateOrUpdateCustomerAction $createOrUpdateCustomer): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        try {
            $billingProfile = $createOrUpdateCustomer->perform(null, InvoicedBillingSystem::ID, $request->request->all());
        } catch (Exception $e) {
            return $this->exceptionResponse($e);
        }

        return new JsonResponse(['id' => $billingProfile->id()]);
    }

    #[Route(path: '/_csadmin/new_company', name: 'csadmin_new_company', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function newAccount(Request $request, CsadminCompanyCreator $companyCreator, string $environment): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $userParams = $this->getUserParams($request);
        $companyParams = $this->getCompanyParams($request, $environment);

        try {
            $company = $companyCreator->create($companyParams, $userParams);
        } catch (Exception $e) {
            return $this->exceptionResponse($e);
        }

        return new JsonResponse(['id' => $company->id()]);
    }

    #[Route(path: '/_csadmin/cancel_account', name: 'csadmin_cancel_account', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function cancelAccount(Request $request, TenantContext $tenant, CancelSubscriptionAction $cancelAction): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $companyId = $request->request->get('company_id');
        $company = Company::find($companyId);
        if (!$company) {
            return new JsonResponse([
                'error' => 'Company # '.$companyId.' does not exist',
            ]);
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $atPeriodEnd = (bool) $request->request->get('at_period_end', false);

        try {
            $cancelAction->cancel($company, 'unspecified', $atPeriodEnd);
        } catch (BillingException $e) {
            return $this->exceptionResponse($e);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route(path: '/_csadmin/mark_fraudulent', name: 'csadmin_mark_fraudulent', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function markFraudulent(Request $request, MarkCompanyFraudulent $command): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $companyId = $request->request->get('company_id');
        $company = Company::find($companyId);
        if (!$company) {
            return new JsonResponse([
                'error' => 'Company # '.$companyId.' does not exist',
            ]);
        }

        $command->markFraud($company);

        return new JsonResponse(['success' => true]);
    }

    #[Route(path: '/_csadmin/export_data', name: 'csadmin_export_data', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function exportData(Request $request, Queue $queue, TenantContext $tenant): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $companyId = $request->request->get('company_id');
        $userId = $request->request->getInt('user_id') ?: null;
        $company = Company::find($companyId);
        if (!$company) {
            return new JsonResponse([
                'error' => 'Company # '.$companyId.' does not exist',
            ]);
        }

        $tenant->set($company);

        BasicExportJob::create($queue, 'company', $userId, [
            'tenant_id' => (string) $companyId,
            'ttl' => 172800, // 2 days
            'type' => 'json',
        ]);

        return new JsonResponse();
    }

    #[Route(path: '/_csadmin/dispute_evidence', name: 'csadmin_dispute_evidence', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function disputeEvidence(Request $request, DisputeEvidenceGenerator $disputeEvidenceGenerator): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $billingProfileId = $request->request->get('billing_profile_id');
        $billingProfile = BillingProfile::find($billingProfileId);
        if (!$billingProfile) {
            return new JsonResponse([
                'error' => 'Billing profile # '.$billingProfileId.' does not exist',
            ]);
        }

        try {
            return new JsonResponse([
                'url' => $disputeEvidenceGenerator->generateToUrl($billingProfile),
            ]);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    #[Route(path: '/_csadmin/respond_to_dispute', name: 'csadmin_respond_to_dispute', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function respondToDispute(Request $request, StripeDisputeHandler $stripeDisputeHandler): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $billingProfileId = $request->request->get('billing_profile_id');
        $billingProfile = BillingProfile::find($billingProfileId);
        if (!$billingProfile) {
            return new JsonResponse([
                'error' => 'Billing profile # '.$billingProfileId.' does not exist',
            ]);
        }

        $stripeDisputeId = $request->request->getString('stripe_dispute_id');

        try {
            $dispute = $stripeDisputeHandler->getStripeDispute($stripeDisputeId);
            $stripeDisputeHandler->updateStripeDispute($dispute, $billingProfile);

            return new JsonResponse([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    #[Route(path: '/_csadmin/new_merchant_account', name: 'csadmin_new_merchant_account', methods: ['POST'])]
    public function newMerchantAccount(Request $request, TenantContext $tenant): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $resolver = new OptionsResolver();
        $resolver->setRequired([
            'tenant_id',
            'gateway',
        ]);
        $resolver->setDefaults([
            'credentials' => [],
            'name' => 'Custom gateway',
        ]);
        $resolver->setAllowedTypes('tenant_id', 'numeric');
        $resolver->setAllowedTypes('gateway', 'string');
        $resolver->setAllowedTypes('credentials', 'array');
        $resolver->setAllowedTypes('name', 'string');

        $result = $resolver->resolve($request->request->all());

        $company = Company::findOrFail($result['tenant_id']);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        // Enable the Flywire payment method when the gateway is Flywire.
        if ('flywire' == $result['gateway']) {
            // Ensure only 1 Flywire merchant account is created
            $merchantAccount = MerchantAccount::where('gateway', FlywireGateway::ID)->oneOrNull();
            if (!$merchantAccount) {
                $merchantAccount = new MerchantAccount();
                $merchantAccount->gateway = $result['gateway'];
                $merchantAccount->gateway_id = '0';
                $merchantAccount->name = 'Flywire Payments';
                $merchantAccount->credentials = new stdClass();
                $merchantAccount->saveOrFail();
            }

            $paymentMethods = [PaymentMethodType::DirectDebit, PaymentMethodType::Card, PaymentMethodType::BankTransfer, PaymentMethodType::Online];
            foreach ($paymentMethods as $type) {
                $paymentMethod = PaymentMethod::where('id', $type->toString())->oneOrNull();
                if (!$paymentMethod) {
                    $paymentMethod = new PaymentMethod();
                    $paymentMethod->tenant_id = $company->id;
                    $paymentMethod->id = $type->toString();
                }

                $paymentMethod->convenience_fee = 0; // convenience fees not allowed with Flywire
                $paymentMethod->setMerchantAccount($merchantAccount);
                $paymentMethod->enabled = true;
                $paymentMethod->saveOrFail();
            }

            $company->features->enable('flywire');
        } else {
            $merchantAccount = new MerchantAccount();
            $merchantAccount->gateway = $result['gateway'];
            $merchantAccount->gateway_id = '0';
            $merchantAccount->name = $result['name'];
            $merchantAccount->credentials = (object) $result['credentials'];
            $merchantAccount->saveOrFail();
        }

        return new JsonResponse($merchantAccount->toArray());
    }

    #[Route(path: '/_csadmin/merchant_accounts/{id}/deleted', name: 'csadmin_undelete_merchant_account', methods: ['POST'])]
    public function undeleteMerchantAccount(Request $request, TenantContext $tenant, int $id): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $resolver = new OptionsResolver();
        $resolver->setRequired([
            'tenant_id',
        ]);
        $resolver->setAllowedTypes('tenant_id', 'numeric');

        $result = $resolver->resolve($request->request->all());

        $company = Company::findOrFail($result['tenant_id']);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $merchantAccount = MerchantAccount::findOrFail($id);
        $merchantAccount->deleted = false;
        $merchantAccount->deleted_at = null;
        $merchantAccount->saveOrFail();

        return new JsonResponse($merchantAccount->toArray());
    }

    #[Route(path: '/_csadmin/adyen_account', name: 'csadmin_lookup_adyen_account', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function lookupAdyenAccount(Request $request, FlywirePaymentsCustomerServiceProfile $profileBuilder, TenantContext $tenant): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $accountId = $request->request->get('account_id');

        $adyenAccount = AdyenAccount::queryWithoutMultitenancyUnsafe()
            ->where('id', $accountId)
            ->oneOrNull();

        if (!$adyenAccount) {
            return new JsonResponse(['error' => 'Flywire Payments account does not exist in our database: '.$accountId]);
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($adyenAccount->tenant());

        try {
            return new JsonResponse($profileBuilder->build($adyenAccount));
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    #[Route(path: '/_csadmin/set_payment_pricing', name: 'csadmin_set_payment_pricing', methods: ['POST'])]
    public function setPaymentPricing(Request $request, TenantContext $tenant, GeneratePricing $generatePricing, bool $adyenLiveMode): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $resolver = new OptionsResolver();
        $resolver->setRequired([
            'tenant_id',
            'card_variable_fee',
            'card_international_added_variable_fee',
            'card_fixed_fee',
            'card_interchange_passthrough',
            'amex_interchange_variable_markup',
            'ach_variable_fee',
            'ach_max_fee',
            'ach_fixed_fee',
            'chargeback_fee',
            'override_split_configuration_id',
        ]);
        $resolver->setAllowedTypes('tenant_id', 'numeric');
        $resolver->setAllowedTypes('card_variable_fee', 'numeric');
        $resolver->setAllowedTypes('card_international_added_variable_fee', 'numeric');
        $resolver->setAllowedTypes('card_fixed_fee', ['numeric', 'null']);
        $resolver->setAllowedTypes('card_interchange_passthrough', 'bool');
        $resolver->setAllowedTypes('amex_interchange_variable_markup', ['numeric', 'null']);
        $resolver->setAllowedTypes('ach_variable_fee', ['numeric', 'null']);
        $resolver->setAllowedTypes('ach_max_fee', ['numeric', 'null']);
        $resolver->setAllowedTypes('ach_fixed_fee', ['numeric', 'null']);
        $resolver->setAllowedTypes('chargeback_fee', 'numeric');
        $resolver->setAllowedTypes('override_split_configuration_id', ['string', 'null']);

        try {
            $parameters = $resolver->resolve($request->request->all());
        } catch (Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()]);
        }

        $company = Company::findOrFail($parameters['tenant_id']);
        unset($parameters['tenant_id']);

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        try {
            // Create or retrieve the split configuration
            $parameters['currency'] = $company->currency;
            $parameters['merchant_account'] = AdyenConfiguration::getMerchantAccount($adyenLiveMode, (string) $company->country);
            $adyenAccount = AdyenAccount::oneOrNull() ?? new AdyenAccount();
            $generatePricing->setPricingOnMerchant($adyenAccount, $parameters);
        } catch (IntegrationApiException $e) {
            return new JsonResponse(['error' => $e->getMessage()]);
        }

        return new JsonResponse(['adyen_account' => $adyenAccount->id]);
    }

    #[Route(path: '/_csadmin/set_statement_descriptor', name: 'csadmin_set_statement_descriptor', methods: ['POST'])]
    public function setStatementDescriptor(Request $request, TenantContext $tenant, GeneratePricing $generatePricing): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $company = Company::findOrFail($request->request->getInt('tenant_id'));

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $adyenAccount = AdyenAccount::one();
        $adyenAccount->statement_descriptor = $request->request->getString('statement_descriptor');
        $adyenAccount->saveOrFail();

        return new JsonResponse(['adyen_account' => $adyenAccount->id]);
    }

    #[Route(path: '/_csadmin/set_top_up_threshold_num_of_days', name: 'csadmin_top_up_threshold_num_of_days', methods: ['POST'])]
    public function setTopUpThresholdNumberOfDays(Request $request, TenantContext $tenant): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $company = Company::findOrFail($request->request->getInt('tenant_id'));

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $adyenAccount = AdyenAccount::one();

        $merchantAccount = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID)
            ->where('gateway_id', '0', '<>')
            ->sort('id ASC')
            ->oneOrNull();
        $merchantAccount->top_up_threshold_num_of_days = $request->request->getInt('top_up_threshold_num_of_days');
        $merchantAccount->saveOrFail();

        return new JsonResponse(['adyen_account' => $adyenAccount->id]);
    }

    #[Route(path: '/_csadmin/install_product', name: 'csadmin_install_product', methods: ['POST'])]
    public function installProduct(Request $request, ProductInstaller $installer): JsonResponse
    {
        $this->authenticateCsadminRequest($request);

        $resolver = new OptionsResolver();
        $resolver->setRequired([
            'tenant_id',
            'product',
        ]);
        $resolver->setAllowedTypes('tenant_id', 'numeric');
        $resolver->setAllowedTypes('product', 'numeric');

        $result = $resolver->resolve($request->request->all());

        try {
            $company = Company::findOrFail($result['tenant_id']);
            $product = Product::findOrFail($result['product']);
            $installer->install($product, $company);

            // Create a zero dollar product pricing plan (if one does not exist)
            $productPricing = ProductPricingPlan::where('tenant_id', $company)
                ->where('product_id', $product)
                ->oneOrNull();
            if (!$productPricing) {
                $productPricing = new ProductPricingPlan();
                $productPricing->tenant = $company;
                $productPricing->product = $product;
                $productPricing->price = 0;
                $productPricing->effective_date = CarbonImmutable::now();
                $productPricing->posted_on = CarbonImmutable::now();
                $productPricing->saveOrFail();
            }
        } catch (InstallProductException $e) {
            return $this->exceptionResponse($e);
        }

        return new JsonResponse(['success' => true]);
    }

    private function authenticateCsadminRequest(Request $request): void
    {
        // authenticate the request using an HMAC SHA256 hash.
        $hash = $request->headers->get('X-Signature');
        if (!$hash) {
            throw new NotFoundHttpException();
        }

        $content = $request->getContent();
        $contentHash = hash_hmac('sha256', $content, $this->csAdminSecret);
        if (!hash_equals($contentHash, $hash)) {
            throw new UnauthorizedHttpException('');
        }
    }

    private function getUserParams(Request $request): array
    {
        return [
            'first_name' => $request->request->get('first_name') ?? '',
            'last_name' => $request->request->get('last_name') ?? '',
            'email' => $request->request->get('email'),
            'ip' => $request->getClientIp(),
        ];
    }

    private function getCompanyParams(Request $request, string $environment): array
    {
        $billingProfile = null;
        if ($billingProfileId = $request->request->get('billing_profile_id')) {
            $billingProfile = BillingProfile::findOrFail($billingProfileId);
        }

        $params = [
            'billing_profile' => $billingProfile,
            'name' => '',
            'country' => $request->request->get('country'),
            'email' => $request->request->get('email'),
            'changeset' => $request->request->all('changeset'),
        ];

        // sandbox accounts should always be in test mode
        if ('sandbox' == $environment) {
            $params['test_mode'] = true;
        }

        return $params;
    }

    private function exceptionResponse(Throwable $e): JsonResponse
    {
        return new JsonResponse(['error' => $e->getMessage()]);
    }
}
