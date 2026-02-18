<?php

namespace App\EntryPoint\Controller;

use App\AccountsReceivable\Libs\CreditNoteCsv;
use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Libs\EstimateCsv;
use App\AccountsReceivable\Libs\InvoiceCsv;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Csv\CsvStreamer;
use App\Core\I18n\PhoneFormatter;
use App\Core\Multitenant\TenantContext;
use App\Core\Pdf\Exception\PdfException;
use App\Core\Pdf\PdfDocumentInterface;
use App\Core\Pdf\PdfStreamer;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\AppUrl;
use App\Core\Utils\RandomString;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\CustomerPortal\Libs\CustomerPortalRateLimiter;
use App\CustomerPortal\Libs\CustomerPortalSymfonyRateLimiter;
use App\CustomerPortal\Libs\SessionHelpers\CustomerPortalUserSessionHelper;
use App\Network\Exception\UblValidationException;
use App\Network\Models\NetworkInvitation;
use App\Network\Ubl\ModelUblTransformer;
use App\Network\Ubl\UblStreamer;
use App\Statements\Enums\StatementType;
use App\Statements\Libs\AbstractStatement;
use App\Statements\Libs\StatementBuilder;
use Carbon\CarbonImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(name: 'client_view_', schemes: '%app.protocol%', host: '%app.domain%')]
class ClientViewController extends AbstractController implements StatsdAwareInterface
{
    use StatsdAwareTrait;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly UserContext $userContext,
        private readonly TranslatorInterface $translator,
        private readonly CustomerPortalRateLimiter $rateLimiter,
        private readonly CustomerPortalSymfonyRateLimiter $customerPortalInvoiceViewLimiter
    ) {
    }

    //
    // Invoice Routes
    //

    #[Route(path: '/invoices/{companyId}/{id}', name: 'invoice', methods: ['GET'])]
    public function viewInvoice(string $companyId, string $id, Request $request): Response
    {
        if ($response = $this->customerPortalInvoiceViewLimiter->shouldApplyCaptcha($request)) {
            return $response;
        }

        $invoice = $this->initializeDocument($companyId, $id, $request, Invoice::class);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        $company = $invoice->tenant();

        return $this->redirectToCustomerPortal($company, 'customer_portal_view_invoice', ['id' => $invoice->client_id]);
    }

    #[Route(path: '/invoices/{companyId}/{id}/save', name: 'save_invoice', methods: ['GET'])]
    public function saveInvoice(string $companyId, string $id, Request $request): Response
    {
        $invoice = $this->initializeDocument($companyId, $id, $request, Invoice::class);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $this->saveToInvoicedWorkflow($invoice);
    }

    #[Route(path: '/invoices/{companyId}/{id}/payment', name: 'invoice_payment', methods: ['GET'])]
    public function payInvoice(string $companyId, string $id, Request $request, CustomerHierarchy $hierarchy): Response
    {
        $invoice = $this->initializeDocument($companyId, $id, $request, Invoice::class);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        $portal = new CustomerPortal($invoice->tenant(), $hierarchy);
        if ($portal->invoicePaymentToItemSelection()) {
            // Redirect to payment item selection page
            return $this->redirectToCustomerPortal($invoice->tenant(), 'customer_portal_payment_select_items_form', [
                'Invoice' => [$invoice->number],
                'customer' => $invoice->customer()->client_id,
            ]);
        }

        // Redirect to invoice payment page
        return $this->redirectToCustomerPortal($invoice->tenant(), 'customer_portal_payment_form', [
            'invoices' => [$invoice->client_id],
            'customer' => $invoice->customer()->client_id,
        ]);
    }

    #[Route(path: '/invoices/{companyId}/{id}/pdf', name: 'invoice_pdf', methods: ['GET'])]
    public function downloadInvoicePdf(string $companyId, string $id, Request $request, CustomerPortalEvents $events): Response
    {
        $invoice = $this->initializeDocument($companyId, $id, $request, Invoice::class);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $this->makePdfResponse($invoice, $invoice->tenant(), $invoice->customer(), $request, $events);
    }

    #[Route(path: '/invoices/{companyId}/{id}/html', name: 'invoice_html', methods: ['GET'])]
    public function downloadInvoiceHtml(string $companyId, string $id, Request $request): Response
    {
        $invoice = $this->initializeDocument($companyId, $id, $request, Invoice::class);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $this->makeHtmlResponse($invoice, $invoice->tenant(), $invoice->customer(), $request);
    }

    #[Route(path: '/invoices/{companyId}/{id}/csv', name: 'invoice_csv', methods: ['GET'])]
    public function downloadInvoiceCsv(string $companyId, string $id, Request $request, CustomerPortalEvents $events): Response
    {
        $invoice = $this->initializeDocument($companyId, $id, $request, Invoice::class);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $this->makeCsvResponse($invoice, $request, $events);
    }

    #[Route(path: '/invoices/{companyId}/{id}/ubl', name: 'invoice_ubl', methods: ['GET'])]
    public function downloadInvoiceUbl(string $companyId, string $id, Request $request, ModelUblTransformer $modelTransformer): Response
    {
        $invoice = $this->initializeDocument($companyId, $id, $request, Invoice::class);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $this->makeUblResponse($modelTransformer, $request, $invoice->customer(), $invoice);
    }

    //
    // Credit Note Routes
    //

    #[Route(path: '/credit_notes/{companyId}/{id}', name: 'credit_note', methods: ['GET'])]
    public function viewCreditNote(string $companyId, string $id, Request $request): Response
    {
        if ($response = $this->customerPortalInvoiceViewLimiter->shouldApplyCaptcha($request)) {
            return $response;
        }

        $creditNote = $this->initializeDocument($companyId, $id, $request, CreditNote::class);
        if ($creditNote instanceof Response) {
            return $creditNote;
        }

        return $this->redirectToCustomerPortal($creditNote->tenant(), 'customer_portal_view_credit_note', ['id' => $creditNote->client_id]);
    }

    #[Route(path: '/credit_notes/{companyId}/{id}/save', name: 'save_credit_note', methods: ['GET'])]
    public function saveCreditNote(string $companyId, string $id, Request $request): Response
    {
        $creditNote = $this->initializeDocument($companyId, $id, $request, CreditNote::class);
        if ($creditNote instanceof Response) {
            return $creditNote;
        }

        return $this->saveToInvoicedWorkflow($creditNote);
    }

    #[Route(path: '/credit_notes/{companyId}/{id}/pdf', name: 'credit_note_pdf', methods: ['GET'])]
    public function downloadCreditNotePdf(string $companyId, string $id, Request $request, CustomerPortalEvents $events): Response
    {
        $creditNote = $this->initializeDocument($companyId, $id, $request, CreditNote::class);
        if ($creditNote instanceof Response) {
            return $creditNote;
        }

        return $this->makePdfResponse($creditNote, $creditNote->tenant(), $creditNote->customer(), $request, $events);
    }

    #[Route(path: '/credit_notes/{companyId}/{id}/html', name: 'credit_note_html', methods: ['GET'])]
    public function downloadCreditNoteHtml(string $companyId, string $id, Request $request): Response
    {
        $creditNote = $this->initializeDocument($companyId, $id, $request, CreditNote::class);
        if ($creditNote instanceof Response) {
            return $creditNote;
        }

        return $this->makeHtmlResponse($creditNote, $creditNote->tenant(), $creditNote->customer(), $request);
    }

    #[Route(path: '/credit_notes/{companyId}/{id}/csv', name: 'credit_note_csv', methods: ['GET'])]
    public function downloadCreditNoteCsv(string $companyId, string $id, Request $request, CustomerPortalEvents $events): Response
    {
        $creditNote = $this->initializeDocument($companyId, $id, $request, CreditNote::class);
        if ($creditNote instanceof Response) {
            return $creditNote;
        }

        return $this->makeCsvResponse($creditNote, $request, $events);
    }

    #[Route(path: '/credit_notes/{companyId}/{id}/ubl', name: 'credit_note_ubl', methods: ['GET'])]
    public function downloadCreditNoteUbl(string $companyId, string $id, Request $request, ModelUblTransformer $modelTransformer): Response
    {
        $creditNote = $this->initializeDocument($companyId, $id, $request, CreditNote::class);
        if ($creditNote instanceof Response) {
            return $creditNote;
        }

        return $this->makeUblResponse($modelTransformer, $request, $creditNote->customer(), $creditNote);
    }

    //
    // Estimate Routes
    //

    #[Route(path: '/estimates/{companyId}/{id}', name: 'estimate', methods: ['GET'])]
    public function viewEstimate(string $companyId, string $id, Request $request): Response
    {
        if ($response = $this->customerPortalInvoiceViewLimiter->shouldApplyCaptcha($request)) {
            return $response;
        }

        $estimate = $this->initializeDocument($companyId, $id, $request, Estimate::class);
        if ($estimate instanceof Response) {
            return $estimate;
        }

        return $this->redirectToCustomerPortal($estimate->tenant(), 'customer_portal_view_estimate', ['id' => $estimate->client_id]);
    }

    #[Route(path: '/estimates/{companyId}/{id}/save', name: 'save_estimate', methods: ['GET'])]
    public function saveEstimate(string $companyId, string $id, Request $request): Response
    {
        $estimate = $this->initializeDocument($companyId, $id, $request, Estimate::class);
        if ($estimate instanceof Response) {
            return $estimate;
        }

        return $this->saveToInvoicedWorkflow($estimate);
    }

    #[Route(path: '/estimates/{companyId}/{id}/pdf', name: 'estimate_pdf', methods: ['GET'])]
    public function downloadEstimatePdf(string $companyId, string $id, Request $request, CustomerPortalEvents $events): Response
    {
        $estimate = $this->initializeDocument($companyId, $id, $request, Estimate::class);
        if ($estimate instanceof Response) {
            return $estimate;
        }

        return $this->makePdfResponse($estimate, $estimate->tenant(), $estimate->customer(), $request, $events);
    }

    #[Route(path: '/estimates/{companyId}/{id}/html', name: 'estimate_html', methods: ['GET'])]
    public function downloadEstimateHtml(string $companyId, string $id, Request $request): Response
    {
        $estimate = $this->initializeDocument($companyId, $id, $request, Estimate::class);
        if ($estimate instanceof Response) {
            return $estimate;
        }

        return $this->makeHtmlResponse($estimate, $estimate->tenant(), $estimate->customer(), $request);
    }

    #[Route(path: '/estimates/{companyId}/{id}/csv', name: 'estimate_csv', methods: ['GET'])]
    public function downloadEstimateCsv(string $companyId, string $id, Request $request, CustomerPortalEvents $events): Response
    {
        $estimate = $this->initializeDocument($companyId, $id, $request, Estimate::class);
        if ($estimate instanceof Response) {
            return $estimate;
        }

        return $this->makeCsvResponse($estimate, $request, $events);
    }

    #[Route(path: '/estimates/{companyId}/{id}/ubl', name: 'estimate_ubl', methods: ['GET'])]
    public function downloadEstimateUbl(string $companyId, string $id, Request $request, ModelUblTransformer $modelTransformer): Response
    {
        $estimate = $this->initializeDocument($companyId, $id, $request, Estimate::class);
        if ($estimate instanceof Response) {
            return $estimate;
        }

        return $this->makeUblResponse($modelTransformer, $request, $estimate->customer(), $estimate);
    }

    //
    // Statement Routes
    //
    #[Route(path: '/statements/{companyId}/{id}', name: 'statement', methods: ['GET'])]
    public function viewStatement(string $companyId, string $id, Request $request, StatementBuilder $builder): Response
    {
        if ($response = $this->customerPortalInvoiceViewLimiter->shouldApplyCaptcha($request)) {
            return $response;
        }

        $statement = $this->initializeStatement($companyId, $id, $request, $builder);
        if ($statement instanceof Response) {
            return $statement;
        }

        return $this->redirectToCustomerPortal($statement->getSendCompany(), 'customer_portal_statement', array_merge($request->query->all(), [
            'id' => $id,
        ]));
    }

    #[Route(path: '/statements/{companyId}/{id}/pdf', name: 'statement_pdf', methods: ['GET'])]
    public function downloadStatementPdf(string $companyId, string $id, Request $request, StatementBuilder $builder, CustomerPortalEvents $events): Response
    {
        $statement = $this->initializeStatement($companyId, $id, $request, $builder);
        if ($statement instanceof Response) {
            return $statement;
        }

        return $this->makePdfResponse($statement, $statement->getSendCompany(), $statement->customer, $request, $events);
    }

    #[Route(path: '/statements/{companyId}/{id}/html', name: 'statement_html', methods: ['GET'])]
    public function downloadStatementHtml(string $companyId, string $id, Request $request, StatementBuilder $builder): Response
    {
        $statement = $this->initializeStatement($companyId, $id, $request, $builder);
        if ($statement instanceof Response) {
            return $statement;
        }

        return $this->makeHtmlResponse($statement, $statement->getSendCompany(), $statement->getSendCustomer(), $request);
    }

    #[Route(path: '/statements/{companyId}/{id}/ubl', name: 'statement_ubl', methods: ['GET'])]
    public function downloadStatementUbl(string $companyId, string $id, Request $request, StatementBuilder $builder, ModelUblTransformer $modelTransformer): Response
    {
        $statement = $this->initializeStatement($companyId, $id, $request, $builder);
        if ($statement instanceof Response) {
            return $statement;
        }

        return $this->makeUblResponse($modelTransformer, $request, $statement->customer, $statement);
    }

    //
    // Payment Routes
    //

    #[Route(path: '/payments/{companyId}/{id}/pdf', name: 'receipt_pdf', methods: ['GET'])]
    public function downloadReceiptPdf(string $companyId, string $id, Request $request, CustomerPortalEvents $events): Response
    {
        $payment = $this->initializePayment($companyId, $id, $request);
        if ($payment instanceof Response) {
            return $payment;
        }

        return $this->makePdfResponse($payment, $payment->tenant(), $payment->customer(), $request, $events);
    }

    //
    // Helper Methods
    //

    private function initializeTenant(string $companyId, Request $request): Company|Response
    {
        $company = Company::where('identifier', $companyId)->oneOrNull();
        if (!$company || !$company->billingStatus()->isActive()) {
            throw new NotFoundHttpException();
        }

        // Do a rate limiting check if CAPTCHA verification is needed
        if ($this->rateLimiter->needsCaptchaVerification($company, (string) $request->getClientIp())) {
            $redirectUrl = $this->rateLimiter->encryptRedirectUrlParameter($request->getUri());

            return new RedirectResponse(AppUrl::get()->build().'/captcha?r='.$redirectUrl);
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $company->useTimezone();
        $request->setLocale($company->getLocale());

        return $company;
    }

    private function initializeCustomer(Customer $customer, Request $request): void
    {
        $request->setLocale($customer->getLocale());
    }

    /**
     * @template T of ReceivableDocument
     *
     * @param class-string<T> $modelClass
     *
     * @return T|Response
     */
    private function initializeDocument(string $companyId, string $id, Request $request, string $modelClass)
    {
        $company = $this->initializeTenant($companyId, $request);
        if ($company instanceof Response) {
            return $company;
        }

        $document = $modelClass::findClientId($id);
        if (!$document || $document->voided) {
            return $this->renderNotFound($company);
        }

        $this->initializeCustomer($document->customer(), $request);

        return $document;
    }

    private function initializeStatement(string $companyId, string $id, Request $request, StatementBuilder $builder): AbstractStatement|Response
    {
        $company = $this->initializeTenant($companyId, $request);
        if ($company instanceof Response) {
            return $company;
        }

        $customer = Customer::findClientId($id);
        if (!$customer) {
            return $this->renderNotFound($company);
        }

        $type = StatementType::tryFrom((string) $request->query->get('type')) ?? StatementType::OpenItem;
        $currency = ((string) $request->query->get('currency')) ?: null;
        $start = $request->query->getInt('start') ?: null;
        $end = $request->query->getInt('end') ?: null;
        $pastDueOnly = 'past_due' == $request->query->get('items');

        // Month selector override. Parses 2021-01
        if ($monthStr = $request->query->get('month')) {
            $month = (int) substr($monthStr, 5, 2);
            $year = (int) substr($monthStr, 0, 4);
            $start = (int) mktime(0, 0, 0, $month, 1, $year);
            $end = min(time(), (int) mktime(23, 59, 59, $month, (int) date('t', $start), $year));
        }

        return $builder->build($customer, $type, $currency, $start, $end, $pastDueOnly);
    }

    private function initializePayment(string $companyId, string $id, Request $request): Payment|Transaction|Response
    {
        $company = $this->initializeTenant($companyId, $request);
        if ($company instanceof Response) {
            return $company;
        }

        $payment = Payment::findClientId($id) ?? Transaction::findClientId($id);
        if (!$payment) {
            return $this->renderNotFound($company);
        }

        if ($payment instanceof Payment && $payment->voided) {
            return $this->renderNotFound($company);
        }

        if ($payment instanceof Transaction && Transaction::STATUS_SUCCEEDED != $payment->status) {
            return $this->renderNotFound($company);
        }

        $customer = $payment->customer();
        if (!$customer) {
            return $this->renderNotFound($company);
        }

        $this->initializeCustomer($customer, $request);

        return $payment;
    }

    private function renderNotFound(Company $company): Response
    {
        $this->statsd->increment('client_view.404');

        return $this->render('clientView/404.html.twig', [
            'user' => $this->userContext->get(),
            'company' => [
                'name' => $company->getDisplayName(),
                'email' => $company->email,
                'phone' => PhoneFormatter::format((string) $company->phone, $company->country),
            ],
        ]);
    }

    private function makePdfResponse(PdfDocumentInterface $document, Company $company, ?Customer $customer, Request $request, CustomerPortalEvents $events): Response
    {
        $this->statsd->increment('client_view.download_pdf');

        try {
            $pdfBuilder = $document->getPdfBuilder();
            if (!$pdfBuilder) {
                throw new PdfException('PDF not supported for this document type');
            }

            // track the event
            if ('0' !== $request->query->get('t') && $customer) {
                $events->track($customer, CustomerPortalEvent::DownloadFile);
            }

            $defaultLocale = $customer?->getLocale() ?? $company->getLocale();
            $locale = $request->query->get('locale', $defaultLocale);

            return (new PdfStreamer())->stream($pdfBuilder, $locale);
        } catch (PdfException $e) {
            return new Response($e->getMessage());
        }
    }

    private function makeHtmlResponse(PdfDocumentInterface $document, Company $company, ?Customer $customer, Request $request): Response
    {
        $this->statsd->increment('client_view.download_html');

        try {
            $pdfBuilder = $document->getPdfBuilder();
            if (!$pdfBuilder) {
                throw new PdfException('HTML not supported for this document type');
            }

            $defaultLocale = $customer?->getLocale() ?? $company->getLocale();
            $locale = $request->query->get('locale', $defaultLocale);

            return new Response($pdfBuilder->toHtml($locale));
        } catch (PdfException $e) {
            return new Response($e->getMessage());
        }
    }

    private function makeCsvResponse(ReceivableDocument $document, Request $request, CustomerPortalEvents $events): Response
    {
        $this->statsd->increment('client_view.download_csv');

        $forCustomer = !$request->query->get('for_company');

        $streamer = new CsvStreamer();
        $csv = match (get_class($document)) {
            CreditNote::class => new CreditNoteCsv($document, $forCustomer, $this->translator),
            Estimate::class => new EstimateCsv($document, $forCustomer, $this->translator),
            Invoice::class => new InvoiceCsv($document, $forCustomer, $this->translator),
            default => throw new NotFoundHttpException(),
        };

        // track the event
        if ('0' !== $request->query->get('t')) {
            $events->track($document->customer(), CustomerPortalEvent::DownloadFile);
        }

        $defaultLocale = $document->customer()->getLocale();
        $locale = $request->query->get('locale', $defaultLocale);

        return $streamer->stream($csv, $locale);
    }

    private function makeUblResponse(ModelUblTransformer $modelTransformer, Request $request, Customer $customer, object $document): Response
    {
        $this->statsd->increment('client_view.download_ubl');

        $defaultLocale = $customer->getLocale();
        $locale = $request->query->get('locale', $defaultLocale);

        try {
            $xml = $modelTransformer->transform($document, ['locale' => $locale]);
        } catch (UblValidationException $e) {
            return new Response($e->getMessage());
        }

        if ($document instanceof ReceivableDocument) {
            $filename = $this->translator->trans('titles.'.$document->object.'_number', ['%number%' => $document->number], 'customer_portal', $locale).'.xml';
        } elseif ($document instanceof AbstractStatement) {
            $filename = 'Statement.xml';
        } else {
            $filename = 'Document.xml';
        }

        return (new UblStreamer())->stream($xml, $filename);
    }

    private function saveToInvoicedWorkflow(ReceivableDocument $document): Response
    {
        $this->statsd->increment('client_view.save_to_invoiced');

        // Obtain an invitation for this customer
        $customer = $document->customer();
        $invitation = NetworkInvitation::where('customer_id', $customer)
            ->where('declined', false)
            ->where('expires_at', CarbonImmutable::now()->toDateTimeString(), '>')
            ->oneOrNull();
        if (!$invitation) {
            $invitation = new NetworkInvitation();
            $invitation->uuid = RandomString::generate(32, RandomString::CHAR_ALNUM);
            $invitation->from_company = $document->tenant();
            $invitation->is_customer = true;
            $invitation->customer = $customer;
            $invitation->expires_at = (new CarbonImmutable('+7 days'));
            $invitation->saveOrFail();
        }

        return $this->redirectToRoute('network_accept_invitation', ['id' => $invitation->uuid]);
    }

    private function redirectToCustomerPortal(Company $company, string $route, array $parameters = []): RedirectResponse
    {
        // Create or find a current customer portal session
        $user = $this->userContext->get();
        $session = null;
        if ($user?->isFullySignedIn()) {
            $session = (new CustomerPortalUserSessionHelper($user))->upsertSession();
        }

        $parameters['subdomain'] = $company->getSubdomainUsername();
        $url = $this->generateUrl($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        $url = $this->applyCustomDomain($company, $url);

        // When a session has been created then redirect to an intermediary
        // URL that will start the customer portal session and then redirect
        // to the target destination.
        if ($session) {
            $url = $this->generateUrl('customer_portal_start_session', [
                'subdomain' => $company->getSubdomainUsername(),
                'id' => $session->identifier,
                'r' => base64_encode($url),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
            $url = $this->applyCustomDomain($company, $url);
        }

        return new RedirectResponse($url);
    }

    /**
     * Swaps customer portal subdomain for custom domain.
     */
    private function applyCustomDomain(Company $company, string $url): string
    {
        if ($domain = $company->custom_domain) {
            $start = strpos($url, '//');
            $end = (int) strpos($url, '/', $start + 2);
            $url = substr_replace($url, "https://$domain", 0, $end);
        }

        return $url;
    }
}
