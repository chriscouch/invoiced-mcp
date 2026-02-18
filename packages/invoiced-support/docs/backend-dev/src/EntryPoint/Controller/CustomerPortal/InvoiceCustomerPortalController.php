<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\ClientView\InvoiceClientViewVariables;
use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Libs\DocumentViewTracker;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Query;
use App\CustomerPortal\Command\SignInCustomer;
use App\CustomerPortal\Libs\CustomerPortalContext;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\CustomerPortal\Libs\CustomerPortalSecurityChecker;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Libs\CommentEmailWriter;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use App\Metadata\Models\CustomField;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class InvoiceCustomerPortalController extends AbstractCustomerPortalController
{
    public function __construct(private readonly PaymentFlowManager $paymentFlowManager, CustomerPortalContext $customerPortalContext, UrlGeneratorInterface $urlGenerator, CustomerPortalSecurityChecker $securityChecker, string $appProtocol)
    {
        parent::__construct($customerPortalContext, $urlGenerator, $securityChecker, $appProtocol);
    }

    #[Route(path: '/invoices', name: 'list_invoices', methods: ['GET'])]
    public function listInvoices(Request $request): Response
    {
        // we clean up session data that might be left over from previous payment attempts
        $request->getSession()->set('payment_form_return', '');
        $portal = $this->customerPortalContext->getOrFail();
        if (!$portal->enabled()) {
            throw new NotFoundHttpException();
        }

        $customer = $portal->getSignedInCustomer();
        if (!$customer) {
            return $this->redirectToLogin($request);
        }

        // parse the filter parameters
        $query = $this->buildInvoicesQuery($portal->getAllowCustomerIds(), $request);
        $total = $query->count();
        $filter = $this->parseFilterParams($request, '/invoices', $total, 'date DESC');

        // execute the query
        $invoices = $this->getInvoices($query, $customer, $filter['perPage'], $filter['page'], $filter['sort'], $request);

        // build the totals
        $currency = $customer->calculatePrimaryCurrency();
        $totals = $this->calculateTotals($invoices, ['total', 'balance'], $currency);

        $company = $portal->company();
        $acceptsPayments = PaymentMethod::acceptsPayments($company);

        // show the customer column if there are any sub-customers
        $showCustomer = Customer::where('parent_customer', $customer)->count() > 0;

        return $this->render('customerPortal/invoices/list.twig', [
            'hasEstimates' => $portal->hasEstimates(),
            'acceptsPayments' => $acceptsPayments,
            'showCustomer' => $showCustomer,
            'total' => $total,
            'results' => $invoices,
            'filter' => $filter,
            'sort' => $filter['sort'],
            'totals' => $totals,
        ]);
    }

    #[Route(path: '/invoices/{id}', name: 'view_invoice', methods: ['GET'])]
    public function viewInvoice(
        Request $request,
        SignInCustomer $signIn,
        UserContext $userContext,
        InvoiceClientViewVariables $viewVariables,
        DocumentViewTracker $documentViewTracker,
        string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();
        $company = $portal->company();

        $invoice = Invoice::findClientId($id);
        if (!$invoice || $invoice->voided) {
            throw new NotFoundHttpException();
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        $customer = $invoice->customer();
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        // Sign the customer into the customer portal temporarily
        $response = new Response();
        $response = $signIn->signIn($customer, $response, true);

        // record the view
        $this->trackDocumentView($invoice, $request, $userContext, $documentViewTracker);

        return $this->render('customerPortal/invoices/view.twig', [
            'dateFormat' => $company->date_format,
            'document' => $viewVariables->make($invoice, $portal, $request),
        ], $response);
    }

    #[Route(path: '/invoices/{id}/comments', name: 'invoice_send_message', methods: ['POST'])]
    public function invoiceSendMessage(Request $request, AttachmentUploader $uploader, CommentEmailWriter $emailWriter, CustomerPortalEvents $events, Connection $database, EmailBodyStorageInterface $storage, string $id): Response
    {
        $invoice = Invoice::findClientId($id);
        if (!$invoice || $invoice->voided) {
            throw new NotFoundHttpException();
        }

        // check the CSRF token
        if (!$this->isCsrfTokenValid('customer_portal_leave_comment', (string) $request->request->get('_csrf_token'))) {
            throw new TokenNotFoundException();
        }

        $email = (string) ($request->request->get('email') ?? $invoice->customer()->email);
        $text = (string) $request->request->get('comment');
        $file = $request->files->get('file');

        try {
            $result = $this->sendMessage($invoice, $email, $text, $file, $uploader, $emailWriter, $events, $storage);
        } catch (Exception $e) {
            $database->setRollbackOnly();
            $result = ['message' => $e->getMessage()];

            return new JsonResponse($result, 400);
        }

        return new JsonResponse($result);
    }

    private function buildInvoicesQuery(array $ids, Request $request): Query
    {
        $query = Invoice::query()
            ->with('customer')
            ->where('customer', $ids)
            ->where('draft', false)
            ->where('voided', false);

        if ($status = $request->query->get('status')) {
            if ('past_due' == $status) {
                $query->where('status', InvoiceStatus::PastDue->value);
            } elseif ('paid' == $status) {
                $query->where('paid', true);
            } elseif ('open' == $status) {
                $query->where('paid', false);
            }
        }

        $this->addSearchToQuery($query, $request);
        $this->addDateRangeToQuery($query, $request);

        return $query;
    }

    /**
     * Fetches the invoices for the client portal view.
     */
    private function getInvoices(Query $query, Customer $customer, int $perPage, int $page, string $sort, Request $request): array
    {
        --$page;

        $query->start($perPage * $page)
            ->sort($sort);

        $fields = CustomField::where('object', ['invoice'])
            ->where('external', true)
            ->all();

        $invoices = [];
        $dateFormat = $customer->tenant()->date_format;
        /** @var Invoice $invoice */
        foreach ($query->first($perPage) as $invoice) {
            $balance = Money::fromDecimal($invoice->currency, $invoice->balance);
            $total = Money::fromDecimal($invoice->currency, $invoice->total);

            $invoices[] = [
                'client_id' => $invoice->client_id,
                'customer' => [
                    'name' => $invoice->customer()->name,
                ],
                'number' => $invoice->number,
                'purchase_order' => $invoice->purchase_order,
                'date' => date($dateFormat, $invoice->date),
                'due_date' => $invoice->due_date ? date($dateFormat, $invoice->due_date) : null,
                'currency' => $invoice->currency,
                'total' => $total->toDecimal(),
                '_total' => $total,
                'balance' => $balance->toDecimal(),
                '_balance' => $balance,
                'status' => $invoice->status,
                'url' => $this->generatePortalContextUrl('customer_portal_view_invoice', [
                    'id' => $invoice->client_id,
                ]),
                'payment_url' => $this->makeInvoicePaymentUrl($invoice),
                'pdf_url' => $invoice->pdf_url.'?locale='.$request->getLocale(),
                'metadata' => array_intersect_key((array) $invoice->metadata, array_flip(array_column($fields->toArray(), 'id')))
            ];
        }

        return $invoices;
    }

    private function makeInvoicePaymentUrl(Invoice $invoice): ?string
    {
        // cannot pay invoices that are paid or have pending payments
        if ($invoice->paid || InvoiceStatus::Pending->value == $invoice->status) {
            return null;
        }


        // if payment flow is pending - we can't pay the invoice should be fully paid
        $amount = $this->paymentFlowManager->getBlockingAmount($invoice, Money::fromDecimal($invoice->currency, 0.01));
        if (!$amount->isZero()) {
            return null;
        }

        return $this->generatePortalContextUrl('customer_portal_payment_form', [
            'invoices' => [$invoice->client_id],
        ]);
    }
}
