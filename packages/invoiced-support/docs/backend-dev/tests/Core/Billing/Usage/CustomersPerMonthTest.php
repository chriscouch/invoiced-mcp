<?php

namespace App\Tests\Core\Billing\Usage;

use App\Companies\Models\Company;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Billing\Usage\CustomersPerMonth;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Tests\AppTestCase;

class CustomersPerMonthTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getUsage(): CustomersPerMonth
    {
        return self::getService('test.billing_usage_factory')->get(UsageType::CustomersPerMonth);
    }

    public function testSupports(): void
    {
        $usage = $this->getUsage();
        $this->assertTrue($usage->supports(MonthBillingPeriod::now()));
    }

    public function testShouldSendOverageNotification(): void
    {
        $usage = $this->getUsage();
        $this->assertTrue($usage->canSendOverageNotification());
    }

    public function testGetUsage(): void
    {
        $usage = $this->getUsage();
        $billingPeriod = MonthBillingPeriod::now();

        $this->assertEquals(0, $usage->calculateUsage(new Company(), $billingPeriod)->count);

        $this->assertEquals(0, $usage->calculateUsage(self::$company, $billingPeriod)->count);
    }

    public function testApplyToCharge(): void
    {
        $usage = $this->getUsage();
        $pricingPlan = new UsagePricingPlan();
        $pricingPlan->threshold = 100;
        $pricingPlan->unit_price = 1.5;
        $charge = new OverageCharge();

        $usage->applyToCharge($pricingPlan, 1234, $charge);

        $this->assertEquals(1.5, $charge->price);
        $this->assertEquals(1134, $charge->quantity);
        $this->assertEquals(1701, $charge->total);
    }

    public function testApplyToChargeNoOverage(): void
    {
        $usage = $this->getUsage();
        $pricingPlan = new UsagePricingPlan();
        $pricingPlan->threshold = 100;
        $pricingPlan->unit_price = 1.5;
        $charge = new OverageCharge();
        $usage->applyToCharge($pricingPlan, 100, $charge);

        $this->assertEquals(1.5, $charge->price);
        $this->assertEquals(0, $charge->quantity);
        $this->assertEquals(0, $charge->total);
    }
}
