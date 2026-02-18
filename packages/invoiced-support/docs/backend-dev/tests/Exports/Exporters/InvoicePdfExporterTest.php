<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Exporters\InvoicePdfExporter;
use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class InvoicePdfExporterTest extends AbstractPdfExporterTest
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
        self::$invoice->metadata = new stdClass();
        self::$invoice->metadata->test = 1234;
        self::$invoice->saveOrFail();
        self::$invoice->void();
    }

    public function testBuild(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
        ];
        $export = $this->verifyBuild($opts);

        $this->assertEquals(1, $export->total_records);
        $this->assertEquals(1, $export->position);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        $database = self::getService('test.database');
        $helper = self::getService('test.attribute_helper');
        $factory = self::getService('test.list_query_builder_factory');

        return new InvoicePdfExporter($storage, $database, $helper, $factory);
    }
}
