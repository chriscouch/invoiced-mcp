<?php

namespace App\Tests\Exports\Exporters;

use App\CashApplication\Models\Payment;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class PaymentExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasInvoice();
        self::$payment = new Payment();
        self::$payment->amount = 100;
        self::$payment->currency = 'usd';
        self::$payment->date = (int) mktime(0, 0, 0, 2, 22, 2021);
        self::$payment->applied_to = [['type' => 'invoice', 'invoice' => self::$invoice, 'amount' => 100]];
        self::$payment->metadata = new stdClass();
        self::$payment->metadata->test = 1234;
        self::$payment->saveOrFail();
    }

    public function testBuild(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
        ];
        $expected = 'customer.name,customer.number,date,currency,amount,balance,reference,method,source,status,notes,metadata.test,applied_to,document_number,credit_note,applied_amount
Sherlock,CUST-00001,2021-02-22,usd,100,0,,other,keyed,applied,,1234,invoice,INV-00001,,100
';
        $this->verifyBuild($expected, $opts);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('payment_csv', $storage);
    }
}
