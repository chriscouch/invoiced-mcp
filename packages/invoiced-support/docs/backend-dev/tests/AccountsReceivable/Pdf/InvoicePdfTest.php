<?php

namespace App\Tests\AccountsReceivable\Pdf;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Pdf\InvoicePdf;
use App\CashApplication\Models\Transaction;
use App\Core\Files\Models\Attachment;
use App\Core\Files\Models\File;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\Themes\AbstractCustomerPdfTest;
use App\Themes\Pdf\AbstractCustomerPdf;
use App\Themes\ValueObjects\PdfTheme;

class InvoicePdfTest extends AbstractCustomerPdfTest
{
    private static Invoice $_invoice;
    private static string $_invoiceName = 'INV-99999';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->number = self::$_invoiceName;
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 103,
                'metadata' => [],
                'amount' => 1,
                'type' => 'service',
                'discounts' => [],
                'taxes' => [],
                'rates' => '',
                'taxable' => true,
                'discountable' => true,
            ],
        ];
        $invoice->saveOrFail();

        $payment = new Transaction();
        $payment->method = PaymentMethod::CHECK;
        $payment->setCustomer(self::$customer);
        $payment->setInvoice($invoice);
        $payment->date = 1568271600;
        $payment->amount = 4;
        $payment->saveOrFail();

        $refund = new Transaction();
        $refund->type = Transaction::TYPE_REFUND;
        $refund->method = PaymentMethod::CHECK;
        $refund->setCustomer(self::$customer);
        $refund->setInvoice($invoice);
        $refund->date = 1568271600;
        $refund->amount = 3;
        $refund->setParentTransaction($payment);
        $refund->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 2]];
        $creditNote->saveOrFail();
        $creditNote->refresh();

        $creditNoteTransaction = new Transaction();
        $creditNoteTransaction->credit_note_id = (int) $creditNote->id();
        $creditNoteTransaction->setInvoice($invoice);
        $creditNoteTransaction->amount = -$creditNote->total;
        $creditNoteTransaction->currency = $creditNote->currency;
        $creditNoteTransaction->type = Transaction::TYPE_ADJUSTMENT;
        $creditNoteTransaction->setCustomer(self::$customer);
        $creditNoteTransaction->saveOrFail();

        $invoice->refresh();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = (int) mktime(0, 0, 0, 3, 12, 2019);
        $installment1->amount = 50;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = (int) mktime(0, 0, 0, 4, 12, 2019);
        $installment2->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];
        $invoice->attachPaymentPlan($paymentPlan, true, true);
        self::$_invoice = $invoice;
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$_invoice->delete();
    }

    protected function getPdfBuilder(): AbstractCustomerPdf
    {
        return new InvoicePdf(self::$_invoice);
    }

    protected function getExpectedFilename(): string
    {
        return 'Invoice '.self::$_invoiceName.'.pdf';
    }

    protected function getExpectedBodyHtmlTemplate(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/invoice.twig');
    }

    protected function getExpectedBodyCss(): string
    {
        return (string) file_get_contents(self::$kernel->getProjectDir().'/templates/pdf/classic/invoice.css');
    }

    protected function getExpectedHtmlParameters(): array
    {
        return json_decode(sprintf(
            '{
            "theme": {
                "from_title":"From",
                "to_title":"Bill To",
                "ship_to_title":"Ship To",
                "customer_number_title":"Account #",
                "show_customer_no":false,
                "invoice_number_title":"Invoice Number",
                "date_title":"Date",
                "due_date_title":"Due Date",
                "date_format":null,
                "purchase_order_title":"Purchase Order",
                "quantity_header":"Quantity",
                "item_header":"Item",
                "unit_cost_header":"Rate",
                "total_title":"Total",
                "amount_header":"Amount",
                "subtotal_title":"Subtotal",
                "notes_title":"Notes",
                "terms_title":"Terms",
                "terms":null,
                "header":"INVOICE",
                "payment_terms_title":"Payment Terms",
                "amount_paid_title":"Amount Paid",
                "balance_title":"Balance Due",
                "header_estimate":"ESTIMATE",
                "estimate_number_title":"Estimate Number",
                "estimate_footer":null,
                "header_receipt":"RECEIPT",
                "amount_title":"Amount",
                "payment_method_title":"Payment Method",
                "check_no_title":"Check #",
                "receipt_footer":"Thank you!",
                "use_translations":true
            },
            "company":{
                "country":"US",
                "currency":"usd",
                "email":"test@example.com",
                "highlight_color":"#303030",
                "language":"en",
                "logo":null,
                "name":"TEST",
                "tax_id":null,
                "username":"%s",
                "url":"http://%s.invoiced.localhost:1234",
                "website":"",
                "phone":""
            },
            "customer":{
                "attention_to":null,
                "autopay":false,
                "avalara_exemption_number": null,
                "chase":true,
                "country":"US",
                "email":"sherlock@example.com",
                "language":null,
                "name":"Sherlock",
                "number":"CUST-00001",
                "payment_terms":null,
                "phone":null,
                "tax_id":null,
                "taxable":true,
                "type":"company",
                "metadata":[]
            },
            "invoice":{
                "attempt_count":0,
                "autopay":true,
                "chase":false,
                "closed":false,
                "created_at":%d,
                "currency":"usd",
                "customer":%d,
                "date":"%s",
                "draft":false,
                "due_date":"Apr 13, 2019",
                "id":%d,
                "name":"Invoice",
                "needs_attention":false,
                "network_document_id":null,
                "next_chase_on":null,
                "next_payment_attempt":null,
                "number":"%s",
                "paid":false,
                "payment_plan":%d,
                "payment_terms":"Payment Plan",
                "purchase_order":null,
                "status":"past_due",
                "object":"invoice",
                "payment_source":null,
                "metadata":[],
                "items":[
                    {
                        "catalog_item":null,
                        "created_at":%s,
                        "description":"test",
                        "discountable":true,
                        "id":%d,
                        "name":"Test Item",
                        "taxable":true,
                        "type":"service",
                        "object":"line_item",
                        "metadata":[],
                        "discounts":[],
                        "taxes":[],
                        "rates":""
                    }
                ],
                "discounts":[],
                "taxes":[],
                "shipping":[],
                "rates":[],
                "show_subtotal":false,
                "custom_fields":[],
                "url":"http://invoiced.localhost:1234/invoices/%s/%s",
                "pdf_url":"http://invoiced.localhost:1234/invoices/%s/%s/pdf",
                "csv_url":"http://invoiced.localhost:1234/invoices/%s/%s/csv",
                "payment_url":"http://invoiced.localhost:1234/invoices/%s/%s/payment",
                "amount_paid":3.0,
                "ship_to":null,
                "payments":[
                    {
                        "name":"CN-00001",
                        "amount":-2.0
                    },
                    {
                        "name":"Payment - Sep 12, 2019",
                        "amount":-4.0
                    },
                    {
                        "name":"Refund - Sep 12, 2019",
                        "amount":3.0
                    },
                    {
                        "name":"Payment - %s",
                        "amount":2.0
                    }
                ]
            },
            "paymentPlan":{
                "created_at":%d,
                "id":%d,
                "status":"pending_signup",
                "object":"payment_plan",
                "approval":null,
                "installments": [
                    {
                        "amount": 50.0,
                        "balance": 50.0,
                        "date": "Mar 12, 2019",
                        "id": %d
                    },
                    {
                        "amount": 50.0,
                        "balance": 50.0,
                        "date": "Apr 12, 2019",
                        "id": %d
                    }
                ]
             }
        }',
            self::$_invoice->tenant()->username,
            self::$_invoice->tenant()->username,
            self::$_invoice->created_at,
            self::$_invoice->customer()->id,
            date('M j, Y', self::$_invoice->created_at),
            self::$_invoice->id,
            self::$_invoiceName,
            self::$_invoice->paymentPlan()->id, /* @phpstan-ignore-line */
            self::$_invoice->created_at,
            self::$_invoice->items[0]->id,
            self::$_invoice->tenant()->identifier,
            self::$_invoice['client_id'],
            self::$_invoice->tenant()->identifier,
            self::$_invoice['client_id'],
            self::$_invoice->tenant()->identifier,
            self::$_invoice['client_id'],
            self::$_invoice->tenant()->identifier,
            self::$_invoice['client_id'],
            date('M j, Y', time()),
            self::$_invoice->paymentPlan()->created_at, /* @phpstan-ignore-line */
            self::$_invoice->paymentPlan()->id, /* @phpstan-ignore-line */
            self::$_invoice->paymentPlan()->installments[0]->id, /* @phpstan-ignore-line */
            self::$_invoice->paymentPlan()->installments[1]->id, /* @phpstan-ignore-line */
        ), true);
    }

    private function _getExpectedMustacheVariables(): array
    {
        $paramaters = $this->getExpectedHtmlParameters();
        $paramaters['company']['address'] = "Company<br />\nAddress<br />\nAustin, TX 78701";
        $paramaters['customer']['address'] = "Test<br />\nAddress<br />\nAustin, TX 78701";
        $paramaters['invoice']['balance'] = '$100.00';
        $paramaters['invoice']['notes'] = '';
        $paramaters['invoice']['subtotal'] = '$103.00';
        $paramaters['invoice']['total'] = '$103.00';
        $paramaters['invoice']['items'][0]['amount'] = '$103.00';
        $paramaters['invoice']['items'][0]['custom_fields'] = [];
        /*
         * these values are empty according to expected
         * behavior at App\AccountsReceivable\Pdf\DocumentPdfVariables
         * @code
         * if ('1' === $item['quantity'] && in_array($item['type'], self::$hideWithSingleQuantity)) {
         */
        $paramaters['invoice']['items'][0]['quantity'] = '';
        $paramaters['invoice']['items'][0]['unit_cost'] = '';
        $paramaters['invoice']['discountedSubtotal'] = '$103.00';
        $paramaters['invoice']['amount_paid'] = '$3.00';
        $paramaters['invoice']['terms'] = '';
        $paramaters['invoice']['customFields'] = [];
        $paramaters['invoice']['late_fees'] = true;
        $paramaters['invoice']['subscription_id'] = null;
        $paramaters['invoice']['payment_url'] = null;

        return $paramaters;
    }

    private function _getExpectedTwigVariables(): array
    {
        $paramaters = $this->getExpectedHtmlParameters();
        $paramaters['company']['address'] = "Company\nAddress\nAustin, TX 78701";
        $paramaters['customer']['address'] = "Test\nAddress\nAustin, TX 78701";
        $paramaters['invoice']['balance'] = (float) 100;
        $paramaters['invoice']['notes'] = null;
        $paramaters['invoice']['subtotal'] = (float) 103;
        $paramaters['invoice']['total'] = (float) 103;
        $paramaters['invoice']['items'][0]['amount'] = 103;
        $paramaters['invoice']['items'][0]['quantity'] = 1;
        $paramaters['invoice']['items'][0]['unit_cost'] = 103;
        $paramaters['invoice']['items'][0]['custom_fields'] = [];
        $paramaters['invoice']['discountedSubtotal'] = 103.00;
        $paramaters['invoice']['terms'] = null;
        $paramaters['invoice']['late_fees'] = true;
        $paramaters['invoice']['subscription_id'] = null;
        $paramaters['invoice']['payment_url'] = null;

        return $paramaters;
    }

    public function testBuildPdfAttachmentOverride(): void
    {
        /** @var InvoicePdf $pdf */
        $pdf = $this->getPdfBuilder();

        $file = new File();
        $file->name = 'Invoice.pdf';
        $file->size = 1024;
        $file->type = 'application/pdf';
        if ($url = getenv('TEST_ATTACHMENT_ENDPOINT')) {
            $file->url = $url.'/custom_pdf_test';
        } else {
            $file->url = 'http://localhost/custom_pdf_test';
        }
        $file->saveOrFail();

        $document = $pdf->getDocument();
        $attachment = new Attachment();
        $attachment->parent_type = $document->object;
        $attachment->parent_id = (int) $document->id();
        $attachment->location = Attachment::LOCATION_PDF;
        $attachment->file_id = (int) $file->id();
        $attachment->saveOrFail();

        $this->assertEquals('this is used by the test suite. do not delete this file.', $pdf->build('en_US'));
    }

    /**
     * parent test it useless since it compares the code against itself.
     */
    public function testGetHtmlTwigParameters(): void
    {
        $pdf = $this->getPdfBuilder();
        $pdfTheme = new PdfTheme('twig', '', '');
        $pdf->setPdfTheme($pdfTheme);
        $parameters = $pdf->getHtmlParameters();
        unset($parameters['invoice']['updated_at']);
        unset($parameters['invoice']['items'][0]['updated_at']);
        unset($parameters['paymentPlan']['updated_at']);
        unset($parameters['paymentPlan']['installments'][0]['updated_at']);
        unset($parameters['paymentPlan']['installments'][1]['updated_at']);
        $expectedParameters = $this->_getExpectedTwigVariables();

        $this->assertEquals($expectedParameters, $parameters);
    }

    public function testGetHtmlMustacheParameters(): void
    {
        $pdf = $this->getPdfBuilder();

        // The HTML parameters are tested as being HTML'ified. This
        // requires the Mustache engine is used. In the future there
        // could be tests for HTML'ified and non-HTML'ified parameters.
        $pdfTheme = new PdfTheme('mustache', '', '');
        $pdf->setPdfTheme($pdfTheme);
        $parameters = $pdf->getHtmlParameters();
        unset($parameters['invoice']['updated_at']);
        unset($parameters['invoice']['items'][0]['updated_at']);
        unset($parameters['paymentPlan']['updated_at']);
        unset($parameters['paymentPlan']['installments'][0]['updated_at']);
        unset($parameters['paymentPlan']['installments'][1]['updated_at']);
        $this->assertEquals(
            $this->_getExpectedMustacheVariables(),
            $parameters
        );
    }

    /**
     * overrides deprecated method.
     *
     * @doesNotPerformAssertions
     */
    public function testGetHtmlParameters(): void
    {
    }
}
