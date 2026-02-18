<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class CustomerExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::$customer->metadata = new stdClass();
        self::$customer->metadata->test = 1234;
        self::$customer->saveOrFail();

        self::hasInvoice();
    }

    public function testBuild(): void
    {
        $expected = 'name,number,email,autopay,payment_terms,type,attention_to,address1,address2,city,state,postal_code,country,tax_id,phone,owner,chasing_cadence,next_chase_step,credit_balance,credit_hold,credit_limit,created_at,notes,currency,"0 - 7 Days","8 - 14 Days","15 - 30 Days","31 - 60 Days","61+ Days",balance,metadata.test
Sherlock,CUST-00001,sherlock@example.com,0,,company,,Test,Address,Austin,TX,78701,US,,,,,,0,0,,'.date('Y-m-d').",,usd,100,0,0,0,0,100,1234\n";
        $this->verifyBuild($expected);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('customer_csv', $storage);
    }
}
