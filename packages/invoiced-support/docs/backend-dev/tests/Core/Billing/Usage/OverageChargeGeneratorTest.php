<?php

namespace App\Tests\Core\Billing\Usage;

use App\Companies\Models\Company;
use App\Core\Billing\Models\CustomerUsageRecord;
use App\Core\Billing\Models\InvoiceUsageRecord;
use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Interfaces\UsageInterface;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\Billing\Usage\OverageChargeGenerator;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Tests\AppTestCase;
use Mockery;

class OverageChargeGeneratorTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getCalculator(): OverageChargeGenerator
    {
        return self::getService('test.billing_overage_charge_generator');
    }

    public function testGenerateAllOverages(): void
    {
        $calculator = $this->getCalculator();
        $billingPeriod = new MonthBillingPeriod('202209');
        $usagePricingPlan1 = new UsagePricingPlan();
        $usagePricingPlan1->tenant = self::$company;
        $usagePricingPlan1->usage_type = UsageType::CustomersPerMonth;
        $usagePricingPlan1->threshold = 100;
        $usagePricingPlan1->unit_price = 2;
        $usagePricingPlan1->saveOrFail();
        $usageRecord = CustomerUsageRecord::getOrCreate(self::$company, $billingPeriod);
        $usageRecord->count = 200;
        $usageRecord->saveOrFail();

        $usagePricingPlan2 = new UsagePricingPlan();
        $usagePricingPlan2->tenant = self::$company;
        $usagePricingPlan2->usage_type = UsageType::InvoicesPerMonth;
        $usagePricingPlan2->threshold = 100;
        $usagePricingPlan2->unit_price = 1;
        $usagePricingPlan2->saveOrFail();
        $usageRecord = InvoiceUsageRecord::getOrCreate(self::$company, $billingPeriod);
        $usageRecord->count = 105;
        $usageRecord->saveOrFail();

        $overages = iterator_to_array($calculator->generateAllOverages($billingPeriod));

        $this->assertCount(0, $overages);
    }

    public function testGenerateOverageNoTenant(): void
    {
        $pricingPlan = new UsagePricingPlan();
        $billingPeriod = MonthBillingPeriod::now();
        $usage = Mockery::mock(UsageInterface::class);
        $usage->shouldReceive('supports')->andReturn(true);

        $this->assertNull($this->getCalculator()->generateOverage($pricingPlan, $billingPeriod, $usage));
    }

    public function testGenerateOverageBillingPeriodNotSupported(): void
    {
        $pricingPlan = new UsagePricingPlan();
        $billingPeriod = MonthBillingPeriod::now();
        $usage = Mockery::mock(UsageInterface::class);
        $usage->shouldReceive('supports')->andReturn(false);

        $this->assertNull($this->getCalculator()->generateOverage($pricingPlan, $billingPeriod, $usage));
    }

    public function testGenerateOverageCanceled(): void
    {
        $pricingPlan = new UsagePricingPlan();
        $pricingPlan->tenant = new Company(['canceled' => true]);
        $billingPeriod = MonthBillingPeriod::now();
        $usage = Mockery::mock(UsageInterface::class);
        $usage->shouldReceive('supports')->andReturn(true);

        $this->assertNull($this->getCalculator()->generateOverage($pricingPlan, $billingPeriod, $usage));
    }

    public function testGenerateOverageTrial(): void
    {
        $pricingPlan = new UsagePricingPlan();
        $pricingPlan->tenant = new Company(['trial_ends' => strtotime('+5 days')]);
        $billingPeriod = MonthBillingPeriod::now();
        $usage = Mockery::mock(UsageInterface::class);
        $usage->shouldReceive('supports')->andReturn(true);

        $this->assertNull($this->getCalculator()->generateOverage($pricingPlan, $billingPeriod, $usage));
    }

    public function testGenerateOverageNone(): void
    {
        $pricingPlan = new UsagePricingPlan();
        $company = new Company(['id' => -1]);
        $company->billing_profile = new BillingProfile(['billing_system' => 'invoiced']);
        $pricingPlan->tenant = $company;
        $pricingPlan->usage_type = UsageType::CustomersPerMonth;
        $billingPeriod = MonthBillingPeriod::now();
        $usage = Mockery::mock(UsageInterface::class);
        $usage->shouldReceive('supports')->andReturn(true);
        $usage->shouldReceive('calculateUsage')->andReturn(new CustomerUsageRecord(['count' => 100]));
        $usage->shouldReceive('applyToCharge');

        $this->assertNull($this->getCalculator()->generateOverage($pricingPlan, $billingPeriod, $usage));
    }

    public function testGenerateOverageDoNotBill(): void
    {
        $pricingPlan = new UsagePricingPlan();
        $company = new Company(['id' => -1]);
        $company->billing_profile = new BillingProfile(['billing_system' => 'invoiced']);
        $pricingPlan->tenant = $company;
        $pricingPlan->usage_type = UsageType::CustomersPerMonth;
        $billingPeriod = new MonthBillingPeriod('202210');
        $usage = Mockery::mock(UsageInterface::class);
        $usage->shouldReceive('supports')->andReturn(true);
        $usage->shouldReceive('calculateUsage')->andReturn(new CustomerUsageRecord(['count' => 100, 'do_not_bill' => true]));
        $usage->shouldReceive('applyToCharge')->andReturnUsing(function ($pricingPlan, $usage, OverageCharge $charge) {
            $charge->quantity = 1234;
            $charge->price = 4.56;
            $charge->total = 789;
        });

        $this->assertNull($this->getCalculator()->generateOverage($pricingPlan, $billingPeriod, $usage));
    }

    public function testGenerateOverageNew(): void
    {
        $pricingPlan = new UsagePricingPlan();
        $company = new Company(['id' => -1]);
        $company->billing_profile = new BillingProfile(['billing_system' => 'invoiced']);
        $pricingPlan->tenant = $company;
        $pricingPlan->usage_type = UsageType::CustomersPerMonth;
        $billingPeriod = new MonthBillingPeriod('202210');
        $usage = Mockery::mock(UsageInterface::class);
        $usage->shouldReceive('supports')->andReturn(true);
        $usage->shouldReceive('calculateUsage')->andReturn(new CustomerUsageRecord(['count' => 100]));
        $usage->shouldReceive('applyToCharge')->andReturnUsing(function ($pricingPlan, $usage, OverageCharge $charge) {
            $charge->quantity = 1234;
            $charge->price = 4.56;
            $charge->total = 789;
        });

        /** @var OverageCharge $charge */
        $charge = $this->getCalculator()->generateOverage($pricingPlan, $billingPeriod, $usage);

        $this->assertInstanceOf(OverageCharge::class, $charge);
        $this->assertFalse($charge->persisted());
        $this->assertEquals('202210', $charge->month);
        $this->assertEquals(-1, $charge->tenant_id);
        $this->assertEquals('invoiced', $charge->billing_system);
        $this->assertEquals(1234, $charge->quantity);
        $this->assertEquals(4.56, $charge->price);
        $this->assertEquals(789, $charge->total);
    }

    public function testGenerateOverageExisting(): void
    {
        $charge = new OverageCharge();
        $charge->tenant_id = self::$company->id;
        $charge->month = '202210';
        $charge->billing_system = 'invoiced';
        $charge->dimension = 'customer';
        $charge->quantity = 1;
        $charge->price = 2;
        $charge->total = 3;
        $charge->saveOrFail();

        $pricingPlan = new UsagePricingPlan();
        $pricingPlan->tenant = self::$company;
        $pricingPlan->usage_type = UsageType::CustomersPerMonth;
        $billingPeriod = new MonthBillingPeriod('202210');
        $usage = Mockery::mock(UsageInterface::class);
        $usage->shouldReceive('supports')->andReturn(true);
        $usage->shouldReceive('calculateUsage')->andReturn(new CustomerUsageRecord(['count' => 100]));
        $usage->shouldReceive('applyToCharge')->andReturnUsing(function ($pricingPlan, $usage, OverageCharge $charge) {
            $charge->quantity = 1234;
            $charge->price = 4.56;
            $charge->total = 789;
        });

        /** @var OverageCharge $charge2 */
        $charge2 = $this->getCalculator()->generateOverage($pricingPlan, $billingPeriod, $usage);

        $this->assertInstanceOf(OverageCharge::class, $charge);
        $this->assertEquals($charge->id(), $charge2->id());
        $this->assertEquals('202210', $charge2->month);
        $this->assertEquals(self::$company->id, $charge2->tenant_id);
        $this->assertEquals('invoiced', $charge2->billing_system);
        $this->assertEquals(1234, $charge2->quantity);
        $this->assertEquals(4.56, $charge2->price);
        $this->assertEquals(789, $charge2->total);
    }

    public function testPeriodStartEnd(): void
    {
        $charge = new OverageCharge();
        $charge->month = '202308';

        $this->assertEquals('2023-08-01T00:00:00-05:00', $charge->getPeriodStart()->toIso8601String());
        $this->assertEquals('2023-08-31T23:59:59-05:00', $charge->getPeriodEnd()->toIso8601String());
    }
}
