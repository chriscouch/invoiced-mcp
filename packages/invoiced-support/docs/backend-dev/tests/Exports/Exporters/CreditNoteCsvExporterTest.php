<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class CreditNoteCsvExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasUnappliedCreditNote();
        $items = self::$creditNote->items();
        $items[] = [
            'name' => 'Test 2',
            'unit_cost' => 5,
        ];
        self::$creditNote->discounts = [['amount' => 1]];
        self::$creditNote->taxes = [['amount' => 2]];
        self::$creditNote->items = $items;
        self::$creditNote->closed = false;
        self::$creditNote->metadata = new stdClass();
        self::$creditNote->metadata->test = 1234;
        self::$creditNote->saveOrFail();
    }

    public function testBuildCreditNote(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
        ];
        $expected = 'customer.name,customer.number,customer.email,customer.address1,customer.address2,customer.city,customer.state,customer.postal_code,customer.country,number,date,currency,subtotal,total,balance,metadata.test
Sherlock,CUST-00001,sherlock@example.com,Test,Address,Austin,TX,78701,US,CN-00001,'.date('Y-m-d').',usd,105,106,106,1234
';
        $this->verifyBuild($expected, $opts);
    }

    public function testBuildLineItem(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
            'detail' => 'line_item',
        ];
        $expected = 'customer.name,customer.number,customer.email,customer.address1,customer.address2,customer.city,customer.state,customer.postal_code,customer.country,number,date,currency,subtotal,total,balance,metadata.test,item,description,quantity,unit_cost,line_total,discount,tax
Sherlock,CUST-00001,sherlock@example.com,Test,Address,Austin,TX,78701,US,CN-00001,'.date('Y-m-d').',usd,105,106,106,1234,"Test Item",test,1,100,100,,
,,,,,,,,,,,,,,,,"Test 2",,1,5,5,,
,,,,,,,,,,,,,,,,Discount,,,,,1,
,,,,,,,,,,,,,,,,"Sales Tax",,,,,,2
';
        $this->verifyBuild($expected, $opts);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('credit_note_csv', $storage);
    }
}
