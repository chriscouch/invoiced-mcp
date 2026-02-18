<?php

namespace App\EntryPoint\Controller;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Models\Tax;
use App\AccountsReceivable\Pdf\CreditNotePdf;
use App\AccountsReceivable\Pdf\EstimatePdf;
use App\AccountsReceivable\Pdf\InvoicePdf;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\CashApplication\Pdf\PaymentPdf;
use App\Companies\Enums\VerificationStatus;
use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Models\Member;
use App\Companies\Verification\EmailVerification;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\TenantContext;
use App\Core\Pdf\Exception\PdfException;
use App\Core\Pdf\PdfStreamer;
use App\Core\RestApi\Models\ApiKey;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\SalesTax\Models\TaxRate;
use App\Statements\Libs\AbstractStatement;
use App\Statements\Libs\StatementBuilder;
use App\Statements\Pdf\StatementPdf;
use App\Statements\StatementLines\BalanceForward\AppliedCreditStatementLine;
use App\Statements\StatementLines\BalanceForward\CreditBalanceAdjustmentStatementLine;
use App\Statements\StatementLines\BalanceForward\InvoiceStatementLine;
use App\Statements\StatementLines\BalanceForward\PaymentStatementLine;
use App\Statements\StatementLines\BalanceForward\PreviousBalanceStatementLine;
use App\Statements\StatementLines\OpenItem\OpenCreditNoteStatementLine;
use App\Statements\StatementLines\OpenItem\OpenInvoiceStatementLine;
use App\Themes\Models\PdfTemplate;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(name: 'dashboard_', schemes: '%app.protocol%', host: '%app.domain%')]
class DashboardController extends AbstractController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    //
    // Dashboard Bootstrapping
    //
    #[Route(path: '/ping', name: 'ping', methods: ['GET'])]
    public function ping(): Response
    {
        // This used to be used by the dashboard to obtain a
        // CSRF token. Currently CSRF protection is disabled in
        // the dashboard. If ever re-enabled then this should return
        // a CSRF token in the `csrf_token` cookie
        // (named differently for each environment).
        return new Response('pong');
    }

    #[Route(path: '/users/current/bootstrap', name: 'bootstrap', methods: ['GET'])]
    public function bootstrap(UserContext $userContext, TenantContext $tenant, Request $request): JsonResponse
    {
        $user = $userContext->get();
        if (!$user?->isFullySignedIn()) {
            // if the user ID is set then the user needs 2fa
            if ($user) {
                return new JsonResponse([
                    'two_factor_required' => true,
                ]);
            }

            return new JsonResponse(['type' => 'authorization_error'], 401);
        }
        $session = $request->getSession();
        $allowedCompanies = $session->get('company_restrictions');

        if ([] === $allowedCompanies) {
            $companies = [];
        } else {
            $uid = (int) $user->id();
            $qry = Company::join(Member::class, 'Companies.id', 'Members.tenant_id')
                ->where('Members.user_id', $uid)
                ->where('(Members.expires = 0 OR Members.expires > "'.time().'")')
                ->sort('Companies.name ASC');
            if (null === $allowedCompanies) {
                $qry->join(CompanySamlSettings::class, 'Companies.id', 'CompanySamlSettings.company_id', 'LEFT JOIN')
                    ->where('(CompanySamlSettings.company_id IS NULL OR CompanySamlSettings.enabled = 0 OR CompanySamlSettings.disable_non_sso = 0)');
            } else {
                $qry->where('Companies.id', $allowedCompanies);
            }
            $companies = $qry->all();
        }

        $companyId = null;
        $companyCandidateId = $request->get('company');
        /** @var Company[] $companies */
        foreach ($companies as $company) {
            if ($company->id == $companyCandidateId) {
                $companyId = $companyCandidateId;
                break;
            }
        }
        // set company as users default company if he still has permissions for it
        if (!$companyId) {
            foreach ($companies as $company) {
                if ($company->id == $user->default_company_id) {
                    $companyId = $user->default_company_id;
                    break;
                }
            }
        }
        if (!$companyId && count($companies) > 0) {
            $companyId = $companies[0]->id;
        }

        $selectedCompany = null;
        $_companies = [];
        foreach ($companies as $company) {
            if ($companyId == $company->id) {
                $selectedCompany = $this->expandCompany($company, (bool) $session->get('remembered'), $userContext, $tenant);
            }
            $_companies[] = [
                'id' => $company->id,
                'name' => $company->name,
                'nickname' => $company->nickname,
                'logo' => $company->logo,
                'canceled' => $company->canceled,
            ];
        }

        return new JsonResponse([
            'user' => $this->expandUser($user),
            'companies' => $_companies,
            'selected_company' => $selectedCompany,
            'from_sso' => null !== $session->get('company_restrictions'),
        ]);
    }

    private function expandUser(User $user): array
    {
        $result = $user->toArray();
        $result['default_company_id'] = $user->default_company_id;

        return $result;
    }

    private function expandCompany(Company $company, bool $rememberMe, UserContext $userContext, TenantContext $tenant): array
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        $result = $company->toArray();

        // look up the member/role and build permissions
        $user = $userContext->getOrFail();
        $member = Member::getForUser($user);
        if (!$member) {
            throw new NotFoundHttpException();
        }
        $result['permissions'] = $member->role()->permissions();
        $result['restriction_mode'] = $member->restriction_mode;

        // build API key
        // remember me sessions allow a longer expiration date (3 days vs 30 minutes)
        if ($rememberMe) {
            $expires = strtotime('+3 days');
        } else {
            $expires = strtotime('+30 minutes');
        }
        $source = ApiKey::SOURCE_DASHBOARD;
        $key = $company->getProtectedApiKey($source, $user, $expires, $rememberMe);
        $result['dashboard_api_key'] = $key->secret;

        // include hidden properties
        $result['billing'] = $company->billing;
        $result['features'] = $company->features->all();
        $result['sso_key'] = $company->sso_key;
        $result['currencies'] = $company->currencies;
        $result['verified_email'] = VerificationStatus::Verified == CompanyEmailAddress::getVerificationStatus($company);

        $result['edit_url'] = $this->generateUrl('onboarding_start', ['companyId' => $company->identifier], UrlGeneratorInterface::ABSOLUTE_URL);
        if ($company->features->has('needs_onboarding')) {
            $result['onboarding_url'] = $result['edit_url'];
        }

        $result['publishable_key'] = $company->getPublishableKey(); // adyen MOTO form

        // IMPORTANT: clear the current tenant after we are done
        $tenant->clear();

        return $result;
    }

    //
    // Company Management
    //
    #[Route(path: '/companies/{companyId}/resendVerificationEmail', name: 'resend_verification_email', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function resendVerificationEmail(TenantContext $tenant, EmailVerification $emailVerification, string $companyId): Response
    {
        $company = Company::find($companyId);
        if (!$company) {
            throw new NotFoundHttpException();
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $tenant->set($company);

        try {
            $emailVerification->start($company);
        } catch (BusinessVerificationException $e) {
            return new JsonResponse(['message' => $e->getMessage()]);
        }

        return new Response('', 204);
    }

    //
    // Sample Documents
    //
    #[Route(path: '/{documentType}/sample', name: 'sample_document', methods: ['POST'], requirements: ['documentType' => 'credit_notes|estimates|invoices'], defaults: ['no_database_transaction' => true])]
    public function sampleDocument(Request $request, TenantContext $tenant, UserContext $userContext, string $documentType): Response
    {
        // Locate company
        $company = $this->getSampleCompany($request, $tenant, $userContext);

        $type = Invoice::class;
        $builderType = InvoicePdf::class;

        if ('estimates' == $documentType) {
            $type = Estimate::class;
            $builderType = EstimatePdf::class;
        } elseif ('credit_notes' == $documentType) {
            $type = CreditNote::class;
            $builderType = CreditNotePdf::class;
        }

        $document = $this->buildSampleDocument($company, $type);

        // customize the theme, if supplied
        $theme = $document->theme();
        if ($themeJson = (string) $request->request->get('theme')) {
            $themeValues = json_decode($themeJson, true);
            $theme->refreshWith($themeValues);
        }

        $streamer = new PdfStreamer();
        $pdf = new $builderType($document); /* @phpstan-ignore-line */

        // add the custom template, if supplied
        if ($pdfTemplateJson = (string) $request->request->get('pdf_template')) {
            $pdfTemplateValues = json_decode($pdfTemplateJson, true);
            $pdfTemplate = new PdfTemplate($pdfTemplateValues);
            $pdf->setPdfTheme($pdfTemplate->toPdfTheme());
        }

        $locale = $company->getLocale();

        try {
            return $streamer->stream($pdf, $locale);
        } catch (PdfException $e) {
            return new Response($e->getMessage());
        }
    }

    private function getSampleCompany(Request $request, TenantContext $tenant, UserContext $userContext): Company
    {
        $company = null;
        if ($cId = $request->request->get('company')) {
            $company = Company::findOrFail($cId);

            // IMPORTANT: set the current tenant to enable multitenant operations
            $tenant->set($company);
        }

        $user = $userContext->get();
        if (!$user) {
            throw new NotFoundHttpException();
        }

        if (!$company || !$company->isMember($user)) {
            throw new NotFoundHttpException();
        }

        return $company;
    }

    private function buildSampleDocument(Company $company, string $model): ReceivableDocument
    {
        /** @var ReceivableDocument $document */
        $document = new $model();
        $document->tenant_id = (int) $company->id();
        $document->currency = $company->currency;
        $document->date = time();
        $document->due_date = strtotime('+14 days'); /* @phpstan-ignore-line */
        $document->setCustomer($this->getSampleCustomer($company));
        $lineItem1 = new LineItem();
        $lineItem1->name = 'Pens';
        $lineItem1->description = 'Blue ink';
        $lineItem1->quantity = 10;
        $lineItem1->unit_cost = 0.90;
        $lineItem1->amount = 9;
        $lineItem2 = new LineItem();
        $lineItem2->name = 'Paper';
        $lineItem2->description = '20 lb., 96 US / 109 Euro Bright';
        $lineItem2->quantity = 2;
        $lineItem2->unit_cost = 50;
        $lineItem2->amount = 100;
        $lineItem3 = new LineItem();
        $lineItem3->name = 'Paper Clips, Box';
        $lineItem3->quantity = 1;
        $lineItem3->unit_cost = 9.95;
        $lineItem3->amount = 9.95;
        $document->setLineItems([$lineItem1, $lineItem2, $lineItem3]);
        $document->subtotal = 118.95;
        $discount = new Discount();
        $discount->tenant_id = (int) $company->id();
        $discount->type = 'discount';
        $discount->amount = 5;
        $discount->rate = 'coupon';
        $coupon = new Coupon();
        $coupon->tenant_id = (int) $company->id();
        $coupon->id = 'coupon';
        $coupon->name = 'Discount';
        $coupon->value = 5;
        $coupon->is_percent = false;
        $document->setDiscounts([$discount]);
        $tax = new Tax();
        $tax->tenant_id = (int) $company->id();
        $tax->amount = 15;
        $tax->rate = 'tax_rate';
        $taxRate = new TaxRate();
        $taxRate->tenant_id = (int) $company->id();
        $taxRate->id = 'tax_rate';
        $taxRate->name = 'Tax';
        $taxRate->value = 8.2;
        $taxRate->is_percent = true;
        $document->setTaxes([$tax]);
        $document->total = 123.29;
        $document->balance = 23.29; /* @phpstan-ignore-line */
        $document->notes = 'Sample Notes: Thank you for your business!';

        if ($document instanceof Invoice) {
            $document->number = 'INV-000001';
            $document->amount_paid = 100;
        } elseif ($document instanceof CreditNote) {
            $document->number = 'CN-000001';
        } elseif ($document instanceof Estimate) {
            $document->number = 'EST-000001';
        }

        return $document;
    }

    #[Route(path: '/statements/sample', name: 'sample_statement', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sampleStatement(Request $request, TranslatorInterface $translator, StatementBuilder $builder, UserContext $userContext, TenantContext $tenant): Response
    {
        // Locate company
        $company = $this->getSampleCompany($request, $tenant, $userContext);

        if ('open_item' == $request->request->get('statement_type')) {
            $statement = $this->buildSampleOpenItemStatement($company, $translator, $builder);
        } else {
            $statement = $this->buildSampleBalanceForwardStatement($company, $translator, $builder);
        }

        // customize the theme, if supplied
        $theme = $statement->theme();
        if ($themeJson = (string) $request->request->get('theme')) {
            $themeValues = json_decode($themeJson, true);
            $theme->refreshWith($themeValues);
        }

        $streamer = new PdfStreamer();
        $pdf = new StatementPdf($statement);

        // add the custom template, if supplied
        if ($pdfTemplateJson = (string) $request->request->get('pdf_template')) {
            $pdfTemplateValues = json_decode($pdfTemplateJson, true);
            $pdfTemplate = new PdfTemplate($pdfTemplateValues);
            $pdf->setPdfTheme($pdfTemplate->toPdfTheme());
        }

        $locale = $company->getLocale();

        try {
            return $streamer->stream($pdf, $locale);
        } catch (PdfException $e) {
            return new Response($e->getMessage());
        }
    }

    private function buildSampleBalanceForwardStatement(Company $company, TranslatorInterface $translator, StatementBuilder $builder): AbstractStatement
    {
        $customer = $this->getSampleCustomer($company);

        $invoice1 = new Invoice();
        $invoice1->tenant_id = (int) $company->id();
        $invoice1->date = (int) mktime(0, 0, 0, 5, 12, (int) date('Y'));
        $invoice1->number = 'INV-000001';
        $invoice1->customer = -1;
        $invoice1->currency = $company->currency;
        $invoice1->total = 100;
        $invoice1->setRelation('customer', $customer);

        $invoice2 = new Invoice();
        $invoice2->tenant_id = (int) $company->id();
        $invoice2->date = (int) mktime(0, 0, 0, 5, 15, (int) date('Y'));
        $invoice2->number = 'INV-000002';
        $invoice2->customer = -1;
        $invoice2->currency = $company->currency;
        $invoice2->total = 250;
        $invoice2->setRelation('customer', $customer);

        $payment1 = new Transaction([
            'type' => Transaction::TYPE_PAYMENT,
            'customer' => -1,
            'method' => PaymentMethod::CHECK,
            'date' => mktime(0, 0, 0, 5, 13, (int) date('Y')),
            'currency' => $company->currency,
            'amount' => 100,
        ]);
        $payment1->setCustomer($customer);
        $payment1->setInvoice($invoice1);

        $credit1 = new Transaction([
            'type' => Transaction::TYPE_ADJUSTMENT,
            'customer' => -1,
            'method' => PaymentMethod::BALANCE,
            'date' => mktime(0, 0, 0, 5, 14, (int) date('Y')),
            'currency' => $company->currency,
            'amount' => -300,
        ]);
        $credit1->setCustomer($customer);

        $adjustment1 = new Transaction([
            'type' => Transaction::TYPE_ADJUSTMENT,
            'customer' => -1,
            'method' => PaymentMethod::BALANCE,
            'date' => mktime(0, 0, 0, 5, 17, (int) date('Y')),
            'currency' => $company->currency,
            'amount' => 10,
        ]);
        $adjustment1->setCustomer($customer);

        $balanceCharge = new Transaction([
            'type' => Transaction::TYPE_CHARGE,
            'customer' => -1,
            'method' => PaymentMethod::BALANCE,
            'date' => mktime(0, 0, 0, 5, 16, (int) date('Y')),
            'currency' => $company->currency,
            'amount' => 200,
        ]);
        $balanceCharge->setCustomer($customer);
        $balanceCharge->setInvoice($invoice2);

        $activity = [
            // previous
            new PreviousBalanceStatementLine($translator, (int) mktime(0, 0, 0, 5, 12, (int) date('Y')), null),
            // invoices
            new InvoiceStatementLine($invoice1, $translator),
            new InvoiceStatementLine($invoice2, $translator),
            // payments
            new PaymentStatementLine($payment1, $translator),
            // credit
            new CreditBalanceAdjustmentStatementLine($credit1, $translator),
            // adjustment
            new CreditBalanceAdjustmentStatementLine($adjustment1, $translator),
            // balance charge
            new AppliedCreditStatementLine($balanceCharge, $translator),
        ];

        $statement = $builder->balanceForward($customer);
        $statement->setLines($activity);

        return $statement;
    }

    private function buildSampleOpenItemStatement(Company $company, TranslatorInterface $translator, StatementBuilder $builder): AbstractStatement
    {
        $customer = $this->getSampleCustomer($company);

        $invoice1 = new Invoice();
        $invoice1->tenant_id = (int) $company->id();
        $invoice1->date = (int) mktime(0, 0, 0, 5, 12, (int) date('Y'));
        $invoice1->due_date = (int) mktime(0, 0, 0, 5, 26, (int) date('Y'));
        $invoice1->number = 'INV-000001';
        $invoice1->customer = -1;
        $invoice1->currency = $company->currency;
        $invoice1->total = 100;
        $invoice1->balance = 100;
        $invoice1->setRelation('customer', $customer);

        $invoice2 = new Invoice();
        $invoice2->tenant_id = (int) $company->id();
        $invoice2->date = (int) mktime(0, 0, 0, 5, 15, (int) date('Y'));
        $invoice2->due_date = (int) mktime(0, 0, 0, 6, 15, (int) date('Y'));
        $invoice2->number = 'INV-000002';
        $invoice2->customer = -1;
        $invoice2->currency = $company->currency;
        $invoice2->total = 250;
        $invoice2->balance = 250;
        $invoice2->setRelation('customer', $customer);

        $creditNote1 = new CreditNote();
        $creditNote1->tenant_id = (int) $company->id();
        $creditNote1->date = (int) mktime(0, 0, 0, 5, 15, (int) date('Y'));
        $creditNote1->number = 'CN-000001';
        $creditNote1->customer = -1;
        $creditNote1->currency = $company->currency;
        $creditNote1->total = 175;
        $creditNote1->balance = 175;
        $creditNote1->setRelation('customer', $customer);

        $activity = [
            // invoices
            new OpenInvoiceStatementLine($invoice1),
            new OpenInvoiceStatementLine($invoice2),
            // credit notes
            new OpenCreditNoteStatementLine($creditNote1),
        ];

        $statement = $builder->openItem($customer);
        $statement->setLines($activity);

        return $statement;
    }

    #[Route(path: '/receipts/sample', name: 'sample_receipt', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function sampleReceipt(Request $request, TenantContext $tenant, UserContext $userContext): Response
    {
        // Locate company
        $company = $this->getSampleCompany($request, $tenant, $userContext);

        $payment = $this->buildSampleReceipt($company);

        // customize the theme, if supplied
        $theme = $payment->theme();
        if ($themeJson = (string) $request->request->get('theme')) {
            $themeValues = json_decode($themeJson, true);
            $theme->refreshWith($themeValues);
        }

        $streamer = new PdfStreamer();
        $pdf = new PaymentPdf($payment);

        // add the custom template, if supplied
        if ($pdfTemplateJson = (string) $request->request->get('pdf_template')) {
            $pdfTemplateValues = json_decode($pdfTemplateJson, true);
            $pdfTemplate = new PdfTemplate($pdfTemplateValues);
            $pdf->setPdfTheme($pdfTemplate->toPdfTheme());
        }

        $locale = $company->getLocale();

        try {
            return $streamer->stream($pdf, $locale);
        } catch (PdfException $e) {
            return new Response($e->getMessage());
        }
    }

    private function buildSampleReceipt(Company $company): Payment
    {
        $payment = new Payment();
        $payment->tenant_id = (int) $company->id();
        $payment->setCustomer($this->getSampleCustomer($company));
        $payment->date = time();
        $payment->currency = $company->currency;
        $payment->amount = 100;
        $payment->method = PaymentMethod::CREDIT_CARD;
        $charge = new Charge();
        $charge->description = 'Test';
        $charge->currency = $company->currency;
        $charge->amount = 100;
        $charge->refunds = [];
        $card = new Card();
        $card->brand = 'Visa';
        $card->last4 = '1234';
        $charge->setPaymentSource($card);
        $payment->charge = $charge;

        return $payment;
    }

    private function getSampleCustomer(Company $company): Customer
    {
        $customer = new Customer(['id' => -1]);
        $customer->tenant_id = (int) $company->id();
        $customer->name = 'Acme, Inc.';
        $customer->number = 'CUST-000001';
        $customer->address1 = '342 Amber St.';
        $customer->address2 = 'Suite 106';
        $customer->city = 'Hill Valley';
        $customer->state = 'CA';
        $customer->postal_code = '94523';
        $customer->country = 'US';

        return $customer;
    }
}
