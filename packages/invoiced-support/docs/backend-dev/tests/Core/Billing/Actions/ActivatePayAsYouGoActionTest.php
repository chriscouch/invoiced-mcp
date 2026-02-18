<?php

namespace App\Tests\Core\Billing\Actions;

use App\Companies\Models\CompanyEmailAddress;
use App\Core\Billing\Action\ActivatePayAsYouGoAction;
use App\Core\Billing\Audit\BillingItemFactory;
use App\Core\Billing\BillingSystem\InvoicedBillingSystem;
use App\Core\Billing\Enums\UsageType;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class ActivatePayAsYouGoActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getAction(InvoicedBillingSystem $billingSystem): ActivatePayAsYouGoAction
    {
        $billingItemFactory = new BillingItemFactory();

        return new ActivatePayAsYouGoAction($billingSystem, $billingItemFactory, self::getService('test.product_installer'));
    }

    public function testActivateMissingFeature(): void
    {
        $this->expectException(BillingException::class);
        $billingSystem = Mockery::mock(InvoicedBillingSystem::class);

        $this->getAction($billingSystem)->activate(self::$company);
    }

    public function testActivateUnverifiedEmail(): void
    {
        $this->expectException(BillingException::class);
        $billingSystem = Mockery::mock(InvoicedBillingSystem::class);
        self::$company->features->enable('not_activated');
        $companyEmail = CompanyEmailAddress::where('email', self::$company->email)->one();
        $companyEmail->verified_at = null;
        $companyEmail->saveOrFail();

        $this->getAction($billingSystem)->activate(self::$company);
    }

    public function testActivate(): void
    {
        $billingSystem = Mockery::mock(InvoicedBillingSystem::class);
        $billingSystem->shouldReceive('createSubscription');
        self::$company->features->enable('not_activated');
        $companyEmail = CompanyEmailAddress::where('email', self::$company->email)->one();
        $companyEmail->verified_at = CarbonImmutable::now();
        $companyEmail->saveOrFail();

        $this->getAction($billingSystem)->activate(self::$company);

        $this->assertFalse(self::$company->features->has('not_activated'));
        $this->assertTrue(in_array('Advanced Accounts Receivable', self::$company->features->allProducts()));
        // should create usage pricing plan
        $usagePricingPlan = UsagePricingPlan::where('tenant_id', self::$company)
            ->where('usage_type', UsageType::MoneyBilledPerMonth->value)
            ->oneOrNull();
        $this->assertInstanceOf(UsagePricingPlan::class, $usagePricingPlan);
        $this->assertEquals(500, $usagePricingPlan->threshold);
        $this->assertEquals(0.01, $usagePricingPlan->unit_price);
    }
}
