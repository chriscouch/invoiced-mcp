<?php

namespace App\Tests\Themes;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Pdf\InvoicePdf;
use App\Tests\AppTestCase;
use App\Core\Pdf\PdfStreamer;
use App\Themes\Pdf\AbstractCustomerPdf;
use Symfony\Component\HttpFoundation\Response;

class PdfStreamerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    protected function getInvoice(): Invoice
    {
        $invoice = new Invoice(['id' => 10]);
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->number = 'INV-0001';
        $invoice->date = time();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';

        return $invoice;
    }

    protected function getPdfBuilder(): AbstractCustomerPdf
    {
        return new InvoicePdf($this->getInvoice());
    }

    public function testStream(): void
    {
        $pdf = $this->getPdfBuilder();

        $streamer = new PdfStreamer();
        $response = $streamer->stream($pdf, 'en_US');

        $this->assertInstanceOf(Response::class, $response);
    }
}
