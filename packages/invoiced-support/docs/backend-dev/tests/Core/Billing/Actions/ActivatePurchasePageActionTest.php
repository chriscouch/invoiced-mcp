<?php

namespace App\Tests\Core\Billing\Actions;

use App\Core\Billing\Action\ActivatePurchasePageAction;
use App\Core\Billing\Action\LocalizedPricingAdjustment;
use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\BillingPaymentTerms;
use App\Core\Billing\Enums\PurchasePageReason;
use App\Core\Entitlements\Models\Product;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class ActivatePurchasePageActionTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getAction(): ActivatePurchasePageAction
    {
        $pricingAdjuster = Mockery::mock(LocalizedPricingAdjustment::class);

        return new ActivatePurchasePageAction($pricingAdjuster);
    }

    /**
     * @dataProvider providePlanSelections
     */
    public function testMakePage(string $plan, BillingInterval $billingInterval, string $productName, int $price, int $users, int $userPrice, int $invoices, int $invoicePrice): void
    {
        $action = $this->getAction();
        $pageContext = $action->makePage(self::$company, $plan, $billingInterval);

        $this->assertEquals(self::$company->billing_profile?->id, $pageContext->billing_profile->id);
        $this->assertEquals(self::$company->id, $pageContext->tenant?->id);
        $this->assertEquals(PurchasePageReason::Activate, $pageContext->reason);
        $this->assertEquals(CarbonImmutable::now()->addDays(7)->format('Y-m-d'), $pageContext->expiration_date->format('Y-m-d'));
        $this->assertEquals('US', $pageContext->country);
        $this->assertEquals(BillingPaymentTerms::AutoPay, $pageContext->payment_terms);
        $product = Product::where('name', $productName)->one();
        $expected = (object) [
            'features' => [],
            'products' => [
                $product->id,
            ],
            'productPrices' => [
                $product->id => [
                    'price' => $price,
                    'annual' => BillingInterval::Yearly == $billingInterval,
                    'custom_pricing' => false,
                ],
            ],
            'replaceExistingProducts' => true,
            'quota' => (object) [
                'users' => $users,
            ],
            'usagePricing' => (object) [
                'user' => [
                    'threshold' => $users,
                    'unit_price' => $userPrice,
                ],
            ],
            'billingInterval' => $billingInterval->value,
        ];
        if ($invoices) {
            $expected->usagePricing->invoice = [
                'threshold' => $invoices,
                'unit_price' => $invoicePrice,
            ];
            $expected->quota->aws_email_daily_limit = $invoices;
        }
        $this->assertEquals($expected, $pageContext->changeset);

        // Calling again should retrieve original page
        $pageContext2 = $action->makePage(self::$company, $plan, $billingInterval);
        $this->assertEquals($pageContext->identifier, $pageContext2->identifier);
    }

    public function providePlanSelections(): array
    {
        return [
            ['growth', BillingInterval::Yearly, 'Growth', 1198800, 5, 3900, 250, 100],
            ['growth', BillingInterval::Monthly, 'Growth', 119900, 5, 3900, 250, 100],
        ];
    }
}
