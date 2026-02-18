<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\ClientView\EstimateClientViewVariables;
use App\AccountsReceivable\Exception\EstimateApprovalFormException;
use App\AccountsReceivable\Libs\DocumentViewTracker;
use App\AccountsReceivable\Libs\EstimateApprovalForm;
use App\AccountsReceivable\Libs\EstimateApprovalFormProcessor;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Query;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalContext;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\CustomerPortal\Libs\CustomerPortalSecurityChecker;
use App\Metadata\Models\CustomField;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Forms\PaymentInfoFormBuilder;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\PaymentProcessing\Libs\PaymentMethodViewFactory;
use App\PaymentProcessing\Libs\PaymentRouter;
use App\PaymentProcessing\Libs\TokenizationFlowManager;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Libs\CommentEmailWriter;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class EstimateCustomerPortalController extends AbstractCustomerPortalController
{
    public function __construct(private readonly PaymentFlowManager $paymentFlowManager, private readonly TranslatorInterface $translator, CustomerPortalContext $customerPortalContext, UrlGeneratorInterface $urlGenerator, CustomerPortalSecurityChecker $securityChecker, string $appProtocol)
    {
        parent::__construct($customerPortalContext, $urlGenerator, $securityChecker, $appProtocol);
    }

    #[Route(path: '/estimates', name: 'list_estimates', methods: ['GET'])]
    public function listEstimates(Request $request): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = $portal->getSignedInCustomer();
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        // parse the filter parameters
        $query = $this->buildEstimatesQuery($portal->getAllowCustomerIds(), $request);
        $total = $query->count();
        $filter = $this->parseFilterParams($request, '/estimates', $total, 'date DESC');

        // execute the query
        $estimates = $this->getEstimates($query, $customer, $filter['perPage'], $filter['page'], $filter['sort'], $request);

        // build the totals
        $currency = $customer->calculatePrimaryCurrency();
        $totals = $this->calculateTotals($estimates, ['total', 'deposit'], $currency);

        // show the customer column if there are any sub-customers
        $showCustomer = Customer::where('parent_customer', $customer)->count() > 0;

        return $this->render('customerPortal/estimates/list.twig', [
            'hasEstimates' => $portal->hasEstimates(),
            'showCustomer' => $showCustomer,
            'results' => $estimates,
            'total' => $total,
            'filter' => $filter,
            'sort' => $filter['sort'],
            'totals' => $totals,
        ]);
    }

    #[Route(path: '/estimates/{id}', name: 'view_estimate', methods: ['GET'])]
    public function viewEstimate(
        Request $request,
        SignInCustomer $signIn,
        UserContext $userContext,
        EstimateClientViewVariables $viewVariables,
        DocumentViewTracker $documentViewTracker,
        string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();

        // look for a estimate
        $estimate = Estimate::findClientId($id);
        if (!$estimate || $estimate->voided) {
            throw new NotFoundHttpException();
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        $customer = $estimate->customer();
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        if (EstimateStatus::EXPIRED == $estimate->status) {
            return $this->render('customerPortal/estimates/expired.twig');
        }

        // sign the customer into the customer portal temporarily
        $response = new Response();
        $signIn->signIn($customer, $response, true);

        // record the view
        $this->trackDocumentView($estimate, $request, $userContext, $documentViewTracker);

        return $this->render('customerPortal/estimates/view.twig', [
            'dateFormat' => $company->date_format,
            'document' => $viewVariables->make($estimate, $portal, $request),
        ], $response);
    }

    #[Route(path: '/estimates/{id}/approve', name: 'estimate_approval_form', methods: ['GET'])]
    public function estimateApprovalForm(PaymentMethodViewFactory $paymentFormFactory, TokenizationFlowManager $tokenizationFlowManager, string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        // look for a estimate
        $estimate = Estimate::findClientId($id);
        if (!$estimate || $estimate->voided) {
            throw new NotFoundHttpException();
        }

        if ($estimate->approved || $estimate->closed || EstimateStatus::EXPIRED == $estimate->status) {
            return new RedirectResponse($this->generatePortalUrl($portal, 'customer_portal_view_estimate', [
                'id' => $estimate->client_id,
            ]));
        }

        if ($estimate->deposit && !$estimate->deposit_paid) {
            $pendingAmount = $this->paymentFlowManager->getBlockingAmount($estimate, Money::fromDecimal($estimate->currency, $estimate->deposit));
            if (!$pendingAmount->isZero()) {
                return $this->render('customerPortal/payments/message.twig', [
                    'message' => strip_tags($this->translator->trans('messages.payment_pending', ['%amount%' => $pendingAmount], 'customer_portal')),
                ]);
            }
        }

        $form = new EstimateApprovalForm($portal, $estimate);
        $customer = $estimate->customer();

        $updateBillingInfoUrl = $this->generatePortalUrl($portal, 'customer_portal_billing_info_form', [
            'id' => $customer->client_id,
            'r' => 'estimate',
            't' => $estimate->client_id.'/approve',
        ]);

        if ($form->useNewEstimateForm()) {
            return $this->render('customerPortal/estimates/approval2.twig', [
                'estimate' => $estimate,
                'backUrl' => $this->generatePortalUrl($portal, 'customer_portal_view_estimate', [
                    'id' => $estimate->client_id,
                ]),
                'approveUrl' => $this->generatePortalUrl($portal, 'customer_portal_approve_estimate_api', [
                    'id' => $estimate->client_id,
                ]),
                'amount' => $estimate->total,
                'terms' => $estimate->theme()->estimate_footer,
                'verifyBillingInfo' => $form->mustVerifyBillingInformation(),
                'address' => $customer->address(true),
                'updateBillingInfoUrl' => $updateBillingInfoUrl,
                'needsPaymentInformation' => $form->needsPaymentInformation(),
                'hasDeposit' => $form->hasDeposit(),
                'deposit' => $estimate->deposit,
            ]);
        }

        // This is the legacy approval workflow where payment information and deposit are built-in.
        return $this->legacyEstimateApproval($portal, $form, $tokenizationFlowManager, $paymentFormFactory, $updateBillingInfoUrl);
    }

    /**
     * @deprecated
     */
    private function legacyEstimateApproval(CustomerPortal $portal, EstimateApprovalForm $form, TokenizationFlowManager $tokenizationFlowManager, PaymentMethodViewFactory $paymentFormFactory, string $updateBillingInfoUrl): Response
    {
        $customer = $form->getCustomer();
        $estimate = $form->getEstimate();
        $builder = new PaymentInfoFormBuilder($portal->getPaymentFormSettings());
        $builder->setCustomer($customer);

        $router = new PaymentRouter();
        // get the deposit payment methods
        $paymentMethods = $form->methods();
        $paymentSourceViews = [];
        foreach ($paymentMethods as $method) {
            $merchantAccount = $router->getMerchantAccount($method, $customer, [$estimate]);
            $gateway = $merchantAccount?->gateway;
            $view = $paymentFormFactory->getPaymentInfoView($method, $gateway);
            if (!$view->shouldBeShown($form->getCompany(), $method, $merchantAccount, $customer)) {
                continue;
            }

            $paymentSourceViews[$method->id] = [
                'view' => $view,
                'method' => $method,
                'merchantAccount' => $merchantAccount,
                'deposit' => true,
            ];
        }

        // get the AutoPay payment methods
        $autoPayMethods = $form->autoPayMethods();
        foreach ($autoPayMethods as $method) {
            $id = $method->id;
            if (isset($paymentSourceViews[$id])) {
                $paymentSourceViews[$id]['autopay'] = true;
            } else {
                $merchantAccount = $router->getMerchantAccount($method, $customer, [$estimate]);
                $gateway = $merchantAccount?->gateway;
                $view = $paymentFormFactory->getPaymentInfoView($method, $gateway);
                if (!$view->shouldBeShown($form->getCompany(), $method, $merchantAccount, $customer)) {
                    continue;
                }

                $paymentSourceViews[$id] = [
                    'view' => $view,
                    'method' => $method,
                    'merchantAccount' => $merchantAccount,
                    'autopay' => true,
                ];
            }
        }

        return $this->render('customerPortal/estimates/approval.twig', [
            'address' => $customer->address(true),
            'amount' => $estimate->total,
            'approveUrl' => $this->generatePortalUrl($portal, 'customer_portal_approve_estimate_api', [
                'id' => $estimate->client_id,
            ]),
            'autoPayMethods' => $autoPayMethods,
            'backUrl' => $this->generatePortalUrl($portal, 'customer_portal_view_estimate', [
                'id' => $estimate->client_id,
            ]),
            'companyObj' => $form->getCompany(),
            'customer' => $customer,
            'deposit' => $estimate->deposit,
            'depositPaymentMethods' => $paymentMethods,
            'estimate' => $estimate,
            'hasDeposit' => $form->hasDeposit(),
            'hasRequiredDeposit' => $form->hasRequiredDeposit(),
            'isAjTutoring' => 'ajtutoring' == $form->getCompany()->getSubdomainUsername(),
            'needsPaymentInformation' => $form->needsPaymentInformation(),
            'paymentInfoForm' => $builder->build(),
            'sourceViews' => $paymentSourceViews,
            'terms' => $estimate->theme()->estimate_footer,
            'tokenizationFlow' => $this->makeTokenizationFlow($tokenizationFlowManager, $customer),
            'updateBillingInfoUrl' => $updateBillingInfoUrl,
            'verifyBillingInfo' => $form->mustVerifyBillingInformation(),
        ]);
    }

    #[Route(path: '/api/estimates/{id}/approve', name: 'approve_estimate_api', methods: ['POST'])]
    public function estimateApprovalApi(Request $request, EstimateApprovalFormProcessor $processor, Connection $database, string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        // look for a estimate
        $estimate = Estimate::findClientId($id);
        if (!$estimate || $estimate->voided) {
            throw new NotFoundHttpException();
        }

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_estimate_approval', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $parameters = $request->request->all();
        $ip = (string) $request->getClientIp();
        $userAgent = (string) $request->headers->get('User-Agent');

        try {
            $processor->handleSubmit($estimate, $parameters, $ip, $userAgent);
        } catch (EstimateApprovalFormException $e) {
            $database->setRollbackOnly();

            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        }

        $form = new EstimateApprovalForm($portal, $estimate);
        if ($form->useNewEstimateForm()) {
            // redirect to the payment page to complete the deposit
            if ($form->hasDeposit()) {
                return new JsonResponse([
                    'url' => $this->generatePortalUrl($portal, 'customer_portal_payment_form', [
                        'estimates' => [$estimate->client_id],
                    ]),
                ]);
            }

            // redirect to the page to add payment information, if no deposit is required
            if ($form->needsPaymentInformation()) {
                return new JsonResponse([
                    'url' => $this->generatePortalUrl($portal, 'customer_portal_update_payment_info_form', [
                        'id' => $estimate->customer()->client_id,
                    ]),
                ]);
            }
        }

        return new JsonResponse([
            'url' => $this->generatePortalUrl($portal, 'customer_portal_view_estimate', [
                'id' => $estimate->client_id,
            ]),
        ]);
    }

    #[Route(path: '/estimates/{id}/comments', name: 'estimate_send_message', methods: ['POST'])]
    public function sendEstimateMessage(Request $request, AttachmentUploader $uploader, CommentEmailWriter $emailWriter, CustomerPortalEvents $events, Connection $database, EmailBodyStorageInterface $storage, string $id): Response
    {
        $estimate = Estimate::findClientId($id);
        if (!$estimate || $estimate->voided) {
            throw new NotFoundHttpException();
        }

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_leave_comment', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $email = (string) ($request->request->get('email') ?? $estimate->customer()->email);
        $text = (string) $request->request->get('comment');
        $file = $request->files->get('file');

        try {
            $result = $this->sendMessage($estimate, $email, $text, $file, $uploader, $emailWriter, $events, $storage);
        } catch (Exception $e) {
            $database->setRollbackOnly();
            $result = ['message' => $e->getMessage()];

            return new JsonResponse($result, 400);
        }

        return new JsonResponse($result);
    }

    private function buildEstimatesQuery(array $ids, Request $request): Query
    {
        $query = Estimate::query()
            ->with('customer')
            ->where('customer', $ids)
            ->where('draft', false)
            ->where('voided', false);

        if ($status = $request->query->get('status')) {
            if ('approved' == $status) {
                $query->where('approved IS NOT NULL');
            } elseif ('open' == $status) {
                $query->where('approved IS NULL')
                    ->where('closed', false);
            }
        }

        $this->addSearchToQuery($query, $request);
        $this->addDateRangeToQuery($query, $request);

        return $query;
    }

    /**
     * Fetches the estimates for the client portal view.
     */
    private function getEstimates(Query $query, Customer $customer, int $perPage, int $page, string $sort, Request $request): array
    {
        --$page;

        $query->start($perPage * $page)
            ->sort($sort);
        $fields = CustomField::where('object', ['estimate'])
            ->where('external', true)
            ->all();

        $estimates = [];
        $dateFormat = $customer->tenant()->date_format;
        /** @var Estimate $estimate */
        foreach ($query->first($perPage) as $estimate) {
            $deposit = Money::fromDecimal($estimate->currency, $estimate->deposit);
            $total = Money::fromDecimal($estimate->currency, $estimate->total);

            $estimates[] = [
                'client_id' => $estimate->client_id,
                'number' => $estimate->number,
                'customer' => [
                    'name' => $estimate->customer()->name,
                ],
                'date' => date($dateFormat, $estimate->date),
                'expiration_date' => $estimate->expiration_date ? date($dateFormat, $estimate->expiration_date) : null,
                'currency' => $estimate->currency,
                'total' => $total->toDecimal(),
                '_total' => $total,
                'deposit' => $deposit->isPositive() ? $deposit->toDecimal() : null,
                '_deposit' => $deposit,
                'status' => $estimate->status,
                'url' => $this->generatePortalContextUrl('customer_portal_view_estimate', [
                    'id' => $estimate->client_id,
                ]),
                'invoice_url' => $this->makeEstimateInvoiceUrl($estimate),
                'approve_url' => $this->makeEstimateApproveUrl($estimate),
                'pdf_url' => $estimate->pdf_url.'?locale='.$request->getLocale(),
                'metadata' => array_intersect_key((array) $estimate->metadata, array_flip(array_column($fields->toArray(), 'id'))),
            ];
        }

        return $estimates;
    }

    private function makeEstimateApproveUrl(Estimate $estimate): ?string
    {
        if (in_array($estimate->status, [EstimateStatus::DECLINED, EstimateStatus::APPROVED, EstimateStatus::EXPIRED])) {
            return null;
        }

        // if payment flow is pending - we can't pay the invoice
        $amount = $this->paymentFlowManager->getBlockingAmount($estimate, Money::fromDecimal($estimate->currency, $estimate->deposit));
        if (!$amount->isZero()) {
            return null;
        }

        return $this->generatePortalContextUrl('customer_portal_estimate_approval_form', [
            'id' => $estimate->client_id,
        ]);
    }

    private function makeEstimateInvoiceUrl(Estimate $estimate): ?string
    {
        if (EstimateStatus::INVOICED != $estimate->status) {
            return null;
        }

        $invoice = $estimate->invoice();

        return $invoice ? $this->generatePortalContextUrl('customer_portal_view_invoice', [
            'id' => $invoice->client_id,
        ]) : null;
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
