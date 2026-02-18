<?php

namespace App\Tests\Exports\Exporters;

use App\Exports\Interfaces\ExporterInterface;
use App\Exports\Libs\ExportStorage;
use stdClass;

class SubscriptionExporterTest extends AbstractCsvExporterTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCustomer();
        self::hasPlan();
        self::hasSubscription();
        self::$subscription->metadata = new stdClass();
        self::$subscription->metadata->test = 1234;
        self::$subscription->saveOrFail();
    }

    public function testBuild(): void
    {
        $expected = 'customer.name,customer.number,customer.email,customer.address1,customer.address2,customer.city,customer.state,customer.postal_code,customer.country,plan,plan.interval_count,plan.interval,plan.currency,recurring_total,mrr,quantity,status,start_date,last_bill,next_bill,current_period_start,current_period_end,cycles,contract_renewal_mode,contract_renewal_cycles,contract_period_start,contract_period_end,cancel_at_period_end,created_at,canceled_at,metadata.test
Sherlock,CUST-00001,sherlock@example.com,Test,Address,Austin,TX,78701,US,starter,2,month,usd,100,50,1,active,'.date('Y-m-d').','.date('Y-m-d').','.date('Y-m-d', strtotime('+2 months')).','.date('Y-m-d').','.date('Y-m-d', strtotime('+2 months') - 86400).',,none,,,,0,'.date('Y-m-d').',,1234
';
        $this->verifyBuild($expected);
    }

    protected function getExporter(ExportStorage $storage): ExporterInterface
    {
        return $this->getExporterById('subscription_csv', $storage);
    }
}
