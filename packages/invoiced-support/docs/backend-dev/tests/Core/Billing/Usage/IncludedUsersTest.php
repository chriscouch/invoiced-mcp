<?php

namespace App\Tests\Core\Billing\Usage;

use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Billing\Usage\IncludedUsers;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Tests\AppTestCase;

class IncludedUsersTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getUsage(): IncludedUsers
    {
        return self::getService('test.billing_usage_factory')->get(UsageType::Users);
    }

    public function testSupports(): void
    {
        $usage = $this->getUsage();
        $this->assertFalse($usage->supports(MonthBillingPeriod::now()));
    }

    public function testShouldSendOverageNotification(): void
    {
        $usage = $this->getUsage();
        $this->assertFalse($usage->canSendOverageNotification());
    }

    public function testGetUsage(): void
    {
        $this->expectException(BillingException::class);
        $usage = $this->getUsage();
        $billingPeriod = MonthBillingPeriod::now();

        $this->assertEquals(0, $usage->calculateUsage(self::$company, $billingPeriod)->count);
    }

    public function testApplyToCharge(): void
    {
        $usage = $this->getUsage();
        $pricingPlan = new UsagePricingPlan();
        $charge = new OverageCharge();

        $usage->applyToCharge($pricingPlan, 1234, $charge);

        $this->assertNull($charge->price);
        $this->assertNull($charge->quantity);
        $this->assertNull($charge->total);
    }
}
