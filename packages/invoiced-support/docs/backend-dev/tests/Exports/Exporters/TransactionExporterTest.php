<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class TransactionExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasInvoice();
        self::hasTransaction();
        self::$transaction->metadata = new stdClass();
        self::$transaction->metadata->test = 1234;
        self::$transaction->saveOrFail();
    }

    public function testBuild(): void
    {
        $opts = [
            'start' => strtotime('-1 month'),
            'end' => strtotime('+1 month'),
        ];
        $expected = 'type,customer.name,customer.number,invoice,date,currency,amount,method,gateway,gateway_id,status,notes,metadata.test
payment,Sherlock,CUST-00001,INV-00001,'.date('Y-m-d').',usd,100,other,,,succeeded,,1234
';
        $this->verifyBuild($expected, $opts);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('transaction_csv', $storage);
    }
}
