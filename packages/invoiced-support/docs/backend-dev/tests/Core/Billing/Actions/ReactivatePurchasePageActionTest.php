<?php

namespace App\Tests\Core\Billing\Actions;

use App\Companies\Models\Company;
use App\Core\Billing\Action\ReactivatePurchasePageAction;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\BillingPaymentTerms;
use App\Core\Billing\Enums\PurchasePageReason;
use App\Core\Billing\Models\BillingProfile;
use App\Core\Billing\Models\ProductPricingPlan;
use App\Core\Entitlements\Models\Product;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ReactivatePurchasePageActionTest extends AppTestCase
{
    private static Product $product;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$product = Product::where('name', 'Advanced Accounts Receivable')->one();
    }

    private function getAction(): ReactivatePurchasePageAction
    {
        return new ReactivatePurchasePageAction();
    }

    public function testCanReactivate(): void
    {
        $action = $this->getAction();
        $this->assertFalse($action->canReactivate(self::$company));

        self::$company->canceled = true;
        self::$company->saveOrFail();
        $this->assertFalse($action->canReactivate(self::$company));

        $productPricing = new ProductPricingPlan();
        $productPricing->tenant = self::$company;
        $productPricing->product = self::$product;
        $productPricing->price = 19;
        $productPricing->annual = false;
        $productPricing->custom_pricing = false;
        $productPricing->effective_date = CarbonImmutable::now();
        $productPricing->posted_on = CarbonImmutable::now();
        $productPricing->saveOrFail();
        $this->assertTrue($action->canReactivate(self::$company));

        $company = new Company();
        $company->billing_profile = new BillingProfile();
        $company->canceled = true;
        $company->test_mode = true;
        $this->assertFalse($action->canReactivate($company));
    }

    public function testCanAutoReactivate(): void
    {
        $action = $this->getAction();
        $this->assertFalse($action->canAutoReactivate(self::$company));

        $company = new Company();
        $company->billing_profile = new BillingProfile();
        $company->canceled = true;
        $company->test_mode = true;
        $this->assertTrue($action->canAutoReactivate($company));
    }

    /**
     * @depends testCanReactivate
     */
    public function testMakePage(): void
    {
        $action = $this->getAction();
        $pageContext = $action->makePage(self::$company);

        $this->assertEquals(self::$company->billing_profile, $pageContext->billing_profile);
        $this->assertEquals(self::$company, $pageContext->tenant);
        $this->assertEquals(PurchasePageReason::Reactivate, $pageContext->reason);
        $this->assertEquals(CarbonImmutable::now()->addDays(7)->format('Y-m-d'), $pageContext->expiration_date->format('Y-m-d'));
        $this->assertEquals('US', $pageContext->country);
        $this->assertEquals(BillingPaymentTerms::AutoPay, $pageContext->payment_terms);
        $expected = (object) [
            'features' => [],
            'products' => [
                self::$product->id,
            ],
            'productPrices' => [
                self::$product->id => [
                    'price' => 1900,
                    'annual' => false,
                    'custom_pricing' => false,
                ],
            ],
            'quota' => (object) [],
            'usagePricing' => (object) [],
            'billingInterval' => BillingInterval::Monthly->value,
        ];
        $this->assertEquals($expected, $pageContext->changeset);

        // Calling again should retrieve original page
        $pageContext2 = $action->makePage(self::$company);
        $this->assertEquals($pageContext->identifier, $pageContext2->identifier);
    }
}
