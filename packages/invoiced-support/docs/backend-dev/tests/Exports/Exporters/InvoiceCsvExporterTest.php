<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class InvoiceCsvExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasInvoice();
        $items = self::$invoice->items();
        $items[] = [
            'name' => 'Test 2',
            'unit_cost' => 5,
        ];
        self::$invoice->items = $items;
        self::$invoice->discounts = [['amount' => 1]];
        self::$invoice->taxes = [['amount' => 2]];
        self::$invoice->metadata = new stdClass();
        self::$invoice->metadata->test = 1234;
        self::$invoice->saveOrFail();
        self::$invoice->void();
    }

    public function testBuildCsvInvoice(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
        ];
        $expected = 'customer.name,customer.number,customer.email,customer.address1,customer.address2,customer.city,customer.state,customer.postal_code,customer.country,number,date,due_date,age,status,currency,subtotal,total,balance,payment_terms,autopay,next_payment_attempt,created_at,metadata.test
Sherlock,CUST-00001,sherlock@example.com,Test,Address,Austin,TX,78701,US,INV-00001,'.date('Y-m-d').',,0,voided,usd,105,106,0,,0,,'.date('Y-m-d').',1234
';
        $this->verifyBuild($expected, $opts);
    }

    public function testBuildCsvLineItem(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
            'detail' => 'line_item',
        ];
        $expected = 'customer.name,customer.number,customer.email,customer.address1,customer.address2,customer.city,customer.state,customer.postal_code,customer.country,number,date,due_date,age,status,currency,subtotal,total,balance,payment_terms,autopay,next_payment_attempt,created_at,metadata.test,item,description,quantity,unit_cost,line_total,discount,tax
Sherlock,CUST-00001,sherlock@example.com,Test,Address,Austin,TX,78701,US,INV-00001,'.date('Y-m-d').',,0,voided,usd,105,106,0,,0,,'.date('Y-m-d').',1234,"Test Item",test,1,100,100,,
,,,,,,,,,,,,,,,,,,,,,,,"Test 2",,1,5,5,,
,,,,,,,,,,,,,,,,,,,,,,,Discount,,,,,1,
,,,,,,,,,,,,,,,,,,,,,,,"Sales Tax",,,,,,2
';
        $this->verifyBuild($expected, $opts);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('invoice_csv', $storage);
    }
}
