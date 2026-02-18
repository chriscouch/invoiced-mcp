<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\AccountsReceivable\Models\Customer;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Exceptions\SignUpFormException;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Opp\OPPClientFactory;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\VerifyBankAccountException;
use App\PaymentProcessing\Forms\PaymentInfoFormBuilder;
use App\PaymentProcessing\Forms\PaymentInfoFormProcessor;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Gateways\OPPGateway;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Libs\PaymentMethodViewFactory;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Libs\TokenizationFlowManager;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\PaymentProcessing\Operations\VerifyBankAccount;
use App\PaymentProcessing\ValueObjects\PaymentInfoForm;
use Doctrine\DBAL\Connection;
use GoCardlessPro\Core\Exception\ApiException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class PaymentInfoCustomerPortalController extends AbstractCustomerPortalController
{
    #[Route(path: '/paymentInfo/{id}', name: 'update_payment_info_form', methods: ['GET'])]
    public function updatePaymentInfoForm(Request $request, SignInCustomer $signIn, PaymentMethodViewFactory $viewFactory, TokenizationFlowManager $tokenizationFlowManager, string $id): Response
    {
        $inputBag = $request->query;
        $portal = $this->customerPortalContext->getOrFail();

        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        $builder = new PaymentInfoFormBuilder($portal->getPaymentFormSettings());
        $builder->setCustomer($customer);

        // sign the customer into the customer portal temporarily
        $response = new Response('');
        $response = $signIn->signIn($customer, $response, true);

        // set the payment method
        if ($methodId = $inputBag->get('method')) {
            $builder->setSelectedPaymentMethod((string) $methodId);
        }

        // set the open modal flag
        if ($inputBag->has('open_modal')) {
            $builder->setOpenModalFlag($inputBag->getBoolean('open_modal'));
        }

        // ensures the AutoPay checkbox is checked
        if ($inputBag->getBoolean('autopay')) {
            $builder->setForceAutoPay();
        }

        // ensures the make default checkbox is checked
        if ($inputBag->has('make_default')) {
            $builder->setMakeDefault('1' == $inputBag->get('make_default'));
        }

        return $this->render(
            'customerPortal/paymentInfo/change.twig',
            $this->getPaymentInfoViewParameters($tokenizationFlowManager, $builder->build(), $viewFactory),
            $response
        );
    }

    /**
     * Gets the parameters needed to render this form.
     */
    public function getPaymentInfoViewParameters(TokenizationFlowManager $tokenizationFlowManager, PaymentInfoForm $form, PaymentMethodViewFactory $viewFactory): array
    {
        $customer = $form->customer;
        if (!($customer instanceof Customer)) {
            throw new RuntimeException('Customer not set on form');
        }

        // get the payment methods
        $paymentMethods = [];
        $paymentSourceViews = [];
        $router = new PaymentRouter();
        foreach ($form->methods as $method) {
            // NOTE: No need to pass documents to `getMerchantAccount` since this
            // form only deals w/ saving payment info.
            $merchantAccount = $router->getMerchantAccount($method, $customer);
            $gateway = $merchantAccount?->gateway;
            $view = $viewFactory->getPaymentInfoView($method, $gateway);
            if (!$view->shouldBeShown($form->company, $method, $merchantAccount, $customer)) {
                continue;
            }

            $paymentSourceViews[] = [
                'view' => $view,
                'method' => $method,
                'merchantAccount' => $merchantAccount,
            ];
            $paymentMethods[$method->id] = [
                'id' => $method->id,
                'name' => $method->toString(),
                'gateway' => $gateway,
            ];
        }

        // determine the selected payment method
        $selectedPaymentMethod = $form->selectedPaymentMethod;
        if (!$selectedPaymentMethod && count($paymentMethods) > 0) {
            $selectedPaymentMethod = array_keys($paymentMethods)[0];
        }

        // get the amount of AutoPay invoices outstanding
        $outstandingAmount = $form->outstandingAutoPayBalance;

        $source = $customer->payment_source;
        $isUpdate = is_object($source);

        return [
            'allowAutoPayEnrollment' => $form->allowAutoPayEnrollment,
            'clientId' => $customer->client_id,
            'companyObj' => $form->company,
            'currency' => $outstandingAmount?->currency,
            'customer' => $customer,
            'hasPaymentSource' => count($customer->paymentSources()) > 0,
            'isUpdate' => $isUpdate,
            'makeDefault' => $form->makeDefault,
            'methods' => $paymentMethods,
            'noPaymentMethods' => 0 === count($paymentMethods),
            'openModal' => $form->openModalFlag,
            'outstandingAmount' => $outstandingAmount?->toDecimal(),
            'paymentInfoForm' => $form,
            'paymentSourceViews' => $paymentSourceViews,
            'selectedPaymentMethod' => $selectedPaymentMethod,
            'shouldEnrollInAutoPay' => $form->forceAutoPay,
            'tokenizationFlow' => $this->makeTokenizationFlow($tokenizationFlowManager, $customer),
        ];
    }

    #[Route(path: '/api/paymentInfo/{id}/{method}', name: 'update_payment_info_api', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function updatePaymentInfoApi(Request $request, PaymentInfoFormProcessor $processor, string $id, string $method): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_payment_info', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException('Customer not found');
        }

        // verify the payment method is enabled and supports vaulting
        $company = $portal->company();
        $method = PaymentMethod::instance($company, $method);

        $builder = new PaymentInfoFormBuilder($portal->getPaymentFormSettings());
        $builder->setCustomer($customer);
        $builder->setMethod($method);

        // attempt to update the source
        try {
            $source = $processor->handleSubmit($builder->build(), $request->request->all());
        } catch (FormException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        }

        $this->addFlash('paymentSourceConnected', '1');

        // check if the payment source needs to be verified
        if ($source->needsVerification()) {
            return new JsonResponse([
                'url' => $this->generatePortalContextUrl(
                    'customer_portal_verify_bank_account_form',
                    [
                        'id' => $id,
                        'bankAccountId' => $source->id(),
                    ]
                ),
            ]);
        }

        // redirect to payment form if set in cookie
        // useful for instant bank account verifications
        $session = $request->getSession();
        if ($session->get('payment_form_return')) {
            return new JsonResponse([
                'url' => $this->returnToPaymentForm($portal, $session, $source),
            ]);
        }

        if (!$portal->enabled()) {
            return new JsonResponse([
                'url' => $this->generatePortalContextUrl(
                    'customer_portal_payment_info_thanks',
                    [
                        'id' => $id,
                    ]
                ),
            ]);
        }

        return new JsonResponse([
            'url' => $this->generatePortalContextUrl(
                'customer_portal_account',
            ),
        ]);
    }

    #[Route(path: '/paymentInfo/{id}/thanks', name: 'payment_info_thanks', methods: ['GET'])]
    public function paymentInfoThanks(CustomerBalanceGenerator $balanceGenerator, string $id): Response
    {
        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        // check if customer owes money
        $balance = $balanceGenerator->generate($customer);
        $paymentUrl = null;
        if ($balance->dueNow->isPositive()) {
            $paymentUrl = '/pay';
        }

        return $this->render('customerPortal/paymentInfo/thanks.twig', [
            'customer' => $customer,
            'paymentUrl' => $paymentUrl,
        ]);
    }

    #[Route(path: '/paymentInfo/{id}/{type}/{sourceId}/makeDefault', name: 'set_default_payment_method', methods: ['POST'])]
    public function setDefaultPaymentMethod(Request $request, Connection $database, string $id, string $type, string $sourceId): Response
    {
        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_my_account', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        $source = $this->loadPaymentSource($customer, $type, $sourceId);
        if (!$source) {
            throw new NotFoundHttpException();
        }

        if (!$customer->setDefaultPaymentSource($source)) {
            $database->setRollbackOnly();

            return new Response('Unable to make payment method the default.');
        }

        return $this->redirectToRoute(
            'customer_portal_account',
            [
                'subdomain' => $customer->tenant()->getSubdomainUsername(),
            ]
        );
    }

    #[Route(path: '/paymentInfo/{id}/{type}/{sourceId}/remove', name: 'delete_payment_method', defaults: ['no_database_transaction' => true], methods: ['POST'])]
    public function deletePaymentMethod(Request $request, DeletePaymentInfo $deletePaymentInfo, string $id, string $type, string $sourceId): Response
    {
        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_my_account', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        $source = $this->loadPaymentSource($customer, $type, $sourceId);
        if (!$source) {
            throw new NotFoundHttpException();
        }

        try {
            $deletePaymentInfo->delete($source);
        } catch (PaymentSourceException $e) {
            return new Response($e->getMessage());
        }

        return $this->redirectToRoute(
            'customer_portal_account',
            [
                'subdomain' => $customer->tenant()->getSubdomainUsername(),
            ]
        );
    }

    #[Route(path: '/paymentInfo/{id}/ach/verify/{bankAccountId}', name: 'verify_bank_account_form', methods: ['GET'])]
    public function verifyBankAccountForm(Request $request, SignInCustomer $signIn, StripeGateway $stripeGateway, string $id, string $bankAccountId): Response
    {
        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        // currently only bank accounts need to be verified
        if ($bankAccountId) {
            $bankAccount = BankAccount::where('id', $bankAccountId)
                ->where('customer_id', $customer)
                ->oneOrNull();
        } else {
            $bankAccount = $customer->payment_source;
        }

        if (!($bankAccount instanceof BankAccount)) {
            throw new NotFoundHttpException();
        }

        if (!$bankAccount->needsVerification()) {
            return new RedirectResponse(
                $this->generatePortalContextUrl('customer_portal_verified_bank_account', [
                    'id' => $id,
                ])
            );
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        // Stripe setup intents have their own URL where they must be verified
        $verifyUrl = null;
        if ('stripe' == $bankAccount->gateway && $bankAccount->gateway_setup_intent) {
            $verifyUrl = $stripeGateway->getSetupIntentNextActionUrl($bankAccount);

            if (!$verifyUrl) {
                return new RedirectResponse(
                    $this->generatePortalContextUrl('customer_portal_verified_bank_account', [
                        'id' => $id,
                    ])
                );
            }
        }

        // sign the customer into the customer portal temporarily
        $response = new Response('');
        $response = $signIn->signIn($customer, $response, true);

        $flashBag = $request->getSession()->getFlashBag(); /* @phpstan-ignore-line */
        $paymentSourceConnected = count($flashBag->get('paymentSourceConnected')) > 0;

        $googleAnalyticsEvents = [];
        if ($paymentSourceConnected) {
            $googleAnalyticsEvents[] = ['category' => 'Customer Portal', 'action' => 'Added Payment Method', 'label' => $customer->name];
        }

        return $this->render('customerPortal/paymentInfo/verifyBankAccount.twig', [
            'customer' => $customer,
            'bankAccount' => $bankAccount,
            'verifyUrl' => $verifyUrl,
            'currencySymbol' => '$', // usd only
            'connected' => $paymentSourceConnected,
            'googleAnalyticsEvents' => $googleAnalyticsEvents,
        ], $response);
    }

    #[Route(path: '/paymentInfo/{id}/ach/verify/{bankAccountId}', name: 'verify_bank_account', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function verifyBankAccount(Request $request, VerifyBankAccount $verifyBankAccount, string $id, string $bankAccountId): Response
    {
        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_verify_payment_method', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        // currently only bank accounts need to be verified
        if ($bankAccountId) {
            $bankAccount = BankAccount::where('id', $bankAccountId)
                ->where('customer_id', $customer)
                ->oneOrNull();
        } else {
            $bankAccount = $customer->payment_source;
        }

        if (!($bankAccount instanceof BankAccount)) {
            throw new NotFoundHttpException();
        }

        if (!$bankAccount->needsVerification()) {
            return new RedirectResponse(
                $this->generatePortalContextUrl('customer_portal_verified_bank_account', [
                    'id' => $id,
                ])
            );
        }

        $amount1 = (int) $request->request->get('amount1');
        $amount2 = (int) $request->request->get('amount2');

        try {
            $verifyBankAccount->verify($bankAccount, $amount1, $amount2);
        } catch (VerifyBankAccountException $e) {
            $this->addFlash('verify_bank_account_errors', $e->getMessage());
            $portal = $this->customerPortalContext->getOrFail();

            return new RedirectResponse(
                $this->generatePortalContextUrl(
                    'customer_portal_verify_bank_account_form',
                    [
                        'id' => $id,
                        'bankAccountId' => $bankAccount->id(),
                    ]
                )
            );
        }

        return new RedirectResponse(
            $this->generatePortalContextUrl('customer_portal_verified_bank_account', [
                'id' => $id,
                'bank_account' => $bankAccountId,
            ])
        );
    }

    #[Route(path: '/paymentInfo/{id}/verified', name: 'verified_bank_account', methods: ['GET'])]
    public function verifiedBankAccount(CustomerBalanceGenerator $balanceGenerator, string $id): Response
    {
        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        // check if customer owes money
        $balance = $balanceGenerator->generate($customer);
        $paymentUrl = null;
        if ($balance->dueNow->isPositive()) {
            $paymentUrl = '/pay';
        }

        return $this->render('customerPortal/paymentInfo/verifiedBankAccount.twig', [
            'customer' => $customer,
            'paymentUrl' => $paymentUrl,
        ]);
    }

    #[Route(path: '/newDirectDebitMandate/{id}', name: 'new_direct_debit_mandate', methods: ['GET'])]
    public function newDirectDebitMandate(TranslatorInterface $translator, GoCardlessGateway $goCardlessGateway, string $id): Response
    {
        // TODO: can convert this to a tokenization flow

        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();
        $paymentMethod = PaymentMethod::instance($company, PaymentMethod::DIRECT_DEBIT);
        /** @var MerchantAccount $merchantAccount */
        $merchantAccount = $paymentMethod->merchantAccount();

        $reason = $translator->trans('messages.gocardless_payment_reason', ['%companyName%' => $company->name], 'customer_portal');

        try {
            // create a gocardless redirect flow where the customer can set up a direct debit mandate
            return new RedirectResponse($goCardlessGateway->makeRedirectFlow($merchantAccount, $customer, $reason));
        } catch (ApiException $e) {
            return new Response('Unable to create mandate: '.$e->getMessage());
        }
    }

    #[Route(path: '/newDirectDebitMandate/{id}/complete', name: 'completed_direct_debit_mandate', methods: ['GET'])]
    public function completedDirectDebitMandate(Request $request, VaultPaymentInfo $paymentInfo, CustomerPortalEvents $events, string $id): Response
    {
        // TODO: can convert this to a tokenization flow

        $inputBag = $request->isMethod('POST') ? $request->request : $request->query;
        $portal = $this->customerPortalContext->getOrFail();

        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        // complete the mandate
        $company = $portal->company();
        $paymentMethod = PaymentMethod::instance($company, PaymentMethod::DIRECT_DEBIT);

        $parameters = [
            'gateway_token' => $inputBag->get('redirect_flow_id'),
        ];

        try {
            $source = $paymentInfo->save($paymentMethod, $customer, $parameters, true);
        } catch (PaymentSourceException $e) {
            return $this->render('customerPortal/error.twig', [
                'message' => 'Unable to complete direct debit mandate: '.$e->getMessage(),
            ]);
        }

        $this->addFlash('paymentSourceConnected', '1');

        // track the event
        $events->track($customer, CustomerPortalEvent::AddPaymentMethod);

        // redirect to payment form if set in session
        $session = $request->getSession();
        if ($session->get('payment_form_return')) {
            return new RedirectResponse($this->returnToPaymentForm($portal, $session, $source));
        }

        if (!$portal->enabled()) {
            return $this->redirectToRoute(
                'customer_portal_verified_bank_account',
                [
                    'subdomain' => $company->getSubdomainUsername(),
                    'id' => $id,
                ]
            );
        }

        return $this->redirectToRoute(
            'customer_portal_account',
            [
                'subdomain' => $company->getSubdomainUsername(),
            ]
        );
    }

    #[Route(path: '/tokenization/{id}/complete', name: 'tokenization_flow_complete', methods: ['GET'])]
    public function tokenizationFlowComplete(Request $request, TokenizationFlowManager $flowManager, string $id): Response
    {
        $tokenizationFlow = TokenizationFlow::where('identifier', $id)->oneOrNull();
        if (!$tokenizationFlow) {
            throw new NotFoundHttpException();
        }

        // Complete the tokenization flow
        $portal = $this->customerPortalContext->getOrFail();
        try {
            $response = $flowManager->handleCompletePage($portal, $tokenizationFlow, $request);
        } catch (FormException|SignUpFormException $e) {
            return $this->render('customerPortal/error.twig', [
                'message' => $e->getMessage(),
            ]);
        }

        if ($response) {
            return $response;
        }

        $this->addFlash('paymentSourceConnected', '1');

        return new RedirectResponse(
            $this->generatePortalContextUrl('customer_portal_account')
        );
    }

    #[Route(path: '/api/opp/customer', name: 'opp_add_customer', methods: ['POST'])]
    public function oppAddCustomer(Request $request, OPPClientFactory $OPPClientFactory, string $oppPublicAccessToken, string $oppPublicKey): JsonResponse
    {
        // Complete the tokenization flow
        $portal = $this->customerPortalContext->getOrFail();
        $customer = $portal->getSignedInCustomer();
        $type = $request->request->get('type');
        $firstName = $request->request->getString('firstName');
        $lastName = $request->request->getString('lastName');
        $method = PaymentMethod::where('id', $type)->one();
        $merchantAccount = (new PaymentRouter())->getMerchantAccount($method, $customer);

        if (!$merchantAccount || $merchantAccount->gateway !== OPPGateway::ID) {
            return new JsonResponse(['message' => 'Unsupported gateway'], 400);
        }

        $token = null;
        $credentials = $merchantAccount->credentials;
        $OPPClient = $OPPClientFactory->createOPPClient($credentials->accessToken ?? '', $credentials->key ?? '');
        try {
            $response = $OPPClient->addCustomer($firstName, $lastName, $customer?->email);
            $token = $response['operationResultObject']['token'] ?? null;
        } catch (IntegrationApiException) {
        }

        return $token ? new JsonResponse([
            'token' => $token,
            'url' => $OPPClient->getOppUiUrl(),
            'authenticationRequest' => [
                'accessToken' => $oppPublicAccessToken,
                'key' => $oppPublicKey,
            ],
        ]) : new JsonResponse(['message' => 'We were unable to tokenize customer.'], 400);
    }

    private function loadPaymentSource(Customer $customer, string $type, string $sourceId): ?PaymentSource
    {
        if ('cards' == $type) {
            return Card::where('id', $sourceId)
                ->where('customer_id', $customer)
                ->where('chargeable', true)
                ->oneOrNull();
        }

        if ('bank_accounts' == $type) {
            return BankAccount::where('id', $sourceId)
                ->where('customer_id', $customer)
                ->where('chargeable', true)
                ->oneOrNull();
        }

        return null;
    }

    private function makeTokenizationFlow(TokenizationFlowManager $tokenizationFlowManager, Customer $customer): TokenizationFlow
    {
        $flow = new TokenizationFlow();
        $flow->customer = $customer;
        $flow->initiated_from = PaymentFlowSource::CustomerPortal;
        $tokenizationFlowManager->create($flow);

        return $flow;
    }
}
