<?php

namespace App\Tests\SubscriptionBilling\BillingMode;

use App\SubscriptionBilling\BillingMode\BillInArrears;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class BillInArrearsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        date_default_timezone_set('UTC');
        self::hasCompany();
    }

    private function getBillingMode(): BillInArrears
    {
        return new BillInArrears();
    }

    public function testBillDate(): void
    {
        $billingMode = $this->getBillingMode();
        $periodStart = new CarbonImmutable('2020-08-21');
        $periodEnd = new CarbonImmutable('2020-08-20T23:59:59Z');
        $this->assertEquals(new CarbonImmutable('2020-08-20T23:59:59Z'), $billingMode->billDateForPeriod($periodStart, $periodEnd));
    }

    public function testChangePeriodEnd(): void
    {
        $billingMode = $this->getBillingMode();

        $periodEnd = new CarbonImmutable('2019-09-30T23:59:59Z');
        $this->assertEquals(new CarbonImmutable('2019-09-30T23:59:59Z'), $billingMode->changePeriodEnd($periodEnd));
    }
}
