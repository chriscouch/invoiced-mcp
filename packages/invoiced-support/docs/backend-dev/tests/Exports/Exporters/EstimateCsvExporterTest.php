<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class EstimateCsvExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasEstimate();
        $items = self::$estimate->items();
        $items[] = [
            'name' => 'Test 2',
            'unit_cost' => 5,
        ];
        self::$estimate->items = $items;
        self::$estimate->discounts = [['amount' => 1]];
        self::$estimate->taxes = [['amount' => 2]];
        self::$estimate->metadata = new stdClass();
        self::$estimate->metadata->test = 1234;
        self::$estimate->saveOrFail();
    }

    public function testBuildCsvEstimate(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
        ];
        $expected = 'customer.name,customer.number,customer.email,customer.address1,customer.address2,customer.city,customer.state,customer.postal_code,customer.country,number,date,expiration_date,currency,subtotal,total,metadata.test
Sherlock,CUST-00001,sherlock@example.com,Test,Address,Austin,TX,78701,US,EST-00001,'.date('Y-m-d').',,usd,105,106,1234
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
        $expected = 'customer.name,customer.number,customer.email,customer.address1,customer.address2,customer.city,customer.state,customer.postal_code,customer.country,number,date,expiration_date,currency,subtotal,total,metadata.test,item,description,quantity,unit_cost,line_total,discount,tax
Sherlock,CUST-00001,sherlock@example.com,Test,Address,Austin,TX,78701,US,EST-00001,'.date('Y-m-d').',,usd,105,106,1234,"Test Item",test,1,100,100,,
,,,,,,,,,,,,,,,,"Test 2",,1,5,5,,
,,,,,,,,,,,,,,,,Discount,,,,,1,
,,,,,,,,,,,,,,,,"Sales Tax",,,,,,2
';
        $this->verifyBuild($expected, $opts);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('estimate_csv', $storage);
    }
}
