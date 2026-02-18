<?php

namespace App\Tests\SubscriptionBilling\BillingMode;

use App\SubscriptionBilling\BillingMode\BillInAdvance;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class BillInAdvanceTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        date_default_timezone_set('UTC');
        self::hasCompany();
    }

    private function getBillingMode(): BillInAdvance
    {
        return new BillInAdvance(0);
    }

    public function testBillDate(): void
    {
        $billingMode = $this->getBillingMode();

        // Test with a past date
        CarbonImmutable::setTestNow('2019-09-01T07:00:00Z');
        $periodStart = new CarbonImmutable('2019-08-01');
        $periodEnd = new CarbonImmutable('2019-08-31T23:59:59Z');
        $this->assertEquals(new CarbonImmutable('2019-08-01'), $billingMode->billDateForPeriod($periodStart, $periodEnd));

        // Test with a future date
        CarbonImmutable::setTestNow('2019-09-01');
        $periodStart = new CarbonImmutable('2019-10-01');
        $periodEnd = new CarbonImmutable('2019-10-31T23:59:59Z');
        $this->assertEquals(new CarbonImmutable('2019-10-01'), $billingMode->billDateForPeriod($periodStart, $periodEnd));

        // Test with bill in advance days and past date
        $billingMode = new BillInAdvance(7);
        CarbonImmutable::setTestNow('2019-09-01');
        $periodStart = new CarbonImmutable('2019-08-01');
        $periodEnd = new CarbonImmutable('2019-09-30T23:59:59Z');
        $this->assertEquals(new CarbonImmutable('2019-08-01'), $billingMode->billDateForPeriod($periodStart, $periodEnd));

        // Test with bill in advance days and future date
        CarbonImmutable::setTestNow('2019-09-01');
        $periodStart = new CarbonImmutable('2019-10-01');
        $periodEnd = new CarbonImmutable('2019-10-31T23:59:59Z');
        $this->assertEquals(new CarbonImmutable('2019-09-24'), $billingMode->billDateForPeriod($periodStart, $periodEnd));
    }

    public function testChangePeriodEnd(): void
    {
        $billingMode = $this->getBillingMode();

        $periodEnd = new CarbonImmutable('2019-08-31T23:59:59Z');
        $this->assertEquals(new CarbonImmutable('2019-09-01'), $billingMode->changePeriodEnd($periodEnd));
    }
}
