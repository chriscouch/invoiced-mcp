<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\AccountsReceivable\Models\Coupon;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ShippingDetail;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ValueObjects\Interval;
use App\Metadata\Models\CustomField;
use App\PaymentProcessing\Models\Card;
use App\SalesTax\Calculator\AvalaraTaxCalculator;
use App\SalesTax\Calculator\InvoicedTaxCalculator;
use App\SalesTax\Libs\TaxCalculatorFactory;
use App\SalesTax\Libs\TaxCalculatorFactoryFacade;
use App\SubscriptionBilling\Libs\SubscriptionInvoice;
use App\SubscriptionBilling\Models\CouponRedemption;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionAddon;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use stdClass;

class SubscriptionInvoiceTest extends AppTestCase
{
    private static Plan $tieredPlan;
    private static Plan $volumePlan;
    private static int $startOfToday;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();
        self::hasCoupon();
        self::hasTaxRate();
        self::hasItem();

        self::$customer->payment_terms = 'NET 14';
        self::$customer->taxes = [self::$taxRate->id];
        self::$customer->city = 'City';
        self::$customer->state = 'State';
        self::$customer->postal_code = '00000';
        self::$customer->country = 'CA';
        self::$customer->saveOrFail();

        self::$plan->description = 'TD';
        self::$plan->notes = 'Notes';
        self::$plan->saveOrFail();

        self::$tieredPlan = new Plan();
        self::$tieredPlan->id = 'tiered';
        self::$tieredPlan->name = 'Tiered';
        self::$tieredPlan->amount = 0;
        self::$tieredPlan->currency = 'usd';
        self::$tieredPlan->interval_count = 2;
        self::$tieredPlan->interval = Interval::MONTH;
        self::$tieredPlan->pricing_mode = Plan::PRICING_TIERED;
        self::$tieredPlan->tiers = [
            (object) [
                'max_qty' => 50,
                'unit_cost' => 100,
            ],
            (object) [
                'min_qty' => 51,
                'max_qty' => 100,
                'unit_cost' => 80,
            ],
            (object) [
                'min_qty' => 101,
                'unit_cost' => 70,
            ],
        ];
        self::$tieredPlan->saveOrFail();

        self::$volumePlan = new Plan();
        self::$volumePlan->id = 'volume';
        self::$volumePlan->name = 'Volume';
        self::$volumePlan->amount = 0;
        self::$volumePlan->currency = 'usd';
        self::$volumePlan->interval_count = 2;
        self::$volumePlan->interval = Interval::MONTH;
        self::$volumePlan->pricing_mode = Plan::PRICING_VOLUME;
        self::$volumePlan->tiers = [
            (object) [
                'max_qty' => 50,
                'unit_cost' => 100,
            ],
            (object) [
                'min_qty' => 51,
                'max_qty' => 100,
                'unit_cost' => 80,
            ],
            (object) [
                'min_qty' => 101,
                'unit_cost' => 70,
            ],
        ];
        self::$volumePlan->saveOrFail();

        self::$company->useTimezone();

        $customField1 = new CustomField();
        $customField1->id = 'account-rep';
        $customField1->object = ObjectType::Invoice->typeName();
        $customField1->name = 'Account Rep';
        $customField1->saveOrFail();

        $start = time();
        self::$startOfToday = (int) mktime(
            0,
            0,
            0,
            (int) date('n', $start),
            (int) date('j', $start),
            (int) date('Y', $start)
        );
    }

    public function testGetInvoiceParameters(): void
    {
        $subInvoice = $this->getSubscriptionInvoice();

        $expected = [
            'subscription_id' => 100,
            'customer' => self::$customer->id(),
            'ship_to' => null,
            'name' => 'Starter',
            'currency' => 'usd',
            'date' => self::$startOfToday,
            'items' => [
                [
                    'plan_id' => self::$plan->internal_id,
                    'plan' => 'starter',
                    'type' => 'plan',
                    'name' => 'Starter',
                    'description' => 'TD',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 10,
                    'unit_cost' => 100,
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'taxes' => [
                [
                    'tax_rate' => self::$taxRate->toArray(),
                ],
            ],
            'notes' => 'Notes',
            'metadata' => (object) ['account-rep' => 'Jan'],
            'draft' => false,
        ];

        $this->assertEquals($expected, $subInvoice->getInvoiceParameters(true, true));
    }

    public function testGetInvoiceParametersNoTaxPreview(): void
    {
        $subInvoice = $this->getSubscriptionInvoice();

        $expected = [
            'subscription_id' => 100,
            'customer' => self::$customer->id(),
            'ship_to' => null,
            'name' => 'Starter',
            'currency' => 'usd',
            'date' => self::$startOfToday,
            'items' => [
                [
                    'plan_id' => self::$plan->internal_id,
                    'plan' => 'starter',
                    'type' => 'plan',
                    'name' => 'Starter',
                    'description' => 'TD',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 10,
                    'unit_cost' => 100,
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'taxes' => [],
            'notes' => 'Notes',
            'metadata' => (object) ['account-rep' => 'Jan'],
            'draft' => false,
        ];

        $this->assertEquals($expected, $subInvoice->getInvoiceParameters(false, true));
    }

    public function testGetInvoiceParametersWithAddons(): void
    {
        $addon1 = new SubscriptionAddon();
        $addon1->catalog_item = self::$item->id;
        $addon2 = new SubscriptionAddon();
        $addon2->catalog_item = self::$item->id;
        $addon2->quantity = 2;
        $addon2->description = 'Test';
        $addons = [
            $addon1,
            $addon2,
        ];

        $subscription = $this->getSubscription();
        $subscription->setAddons($addons);
        $subInvoice = new SubscriptionInvoice($subscription);

        $expected = [
            'subscription_id' => 100,
            'customer' => self::$customer->id(),
            'ship_to' => null,
            'name' => 'Starter',
            'currency' => 'usd',
            'date' => self::$startOfToday,
            'items' => [
                [
                    'plan_id' => self::$plan->internal_id,
                    'plan' => 'starter',
                    'type' => 'plan',
                    'name' => 'Starter',
                    'description' => 'TD',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 10,
                    'unit_cost' => 100,
                    'metadata' => new stdClass(),
                ],
                [
                    'catalog_item_id' => self::$item->internal_id,
                    'catalog_item' => 'test-item',
                    'type' => null,
                    'name' => 'Test Item',
                    'description' => 'Description',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 1,
                    'unit_cost' => 1000,
                    'discountable' => true,
                    'taxable' => true,
                    'taxes' => [],
                ],
                [
                    'catalog_item_id' => self::$item->internal_id,
                    'catalog_item' => 'test-item',
                    'type' => null,
                    'name' => 'Test Item',
                    'description' => "Description\nTest",
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 2,
                    'unit_cost' => 1000,
                    'discountable' => true,
                    'taxable' => true,
                    'taxes' => [],
                ],
            ],
            'discounts' => [],
            'taxes' => [
                [
                    'tax_rate' => self::$taxRate->toArray(),
                ],
            ],
            'notes' => 'Notes',
            'metadata' => (object) ['account-rep' => 'Jan'],
            'draft' => false,
        ];

        $this->assertEquals($expected, $subInvoice->getInvoiceParameters(true, true));
    }

    public function testGetInvoiceParametersCalendarBilling(): void
    {
        $subscription = $this->getSubscription();
        $subscription->snap_to_nth_day = 1;
        $subscription->start_date = (int) mktime(0, 0, 0, 1, 2, 2017);
        $subscription->period_start = (int) mktime(0, 0, 0, 1, 2, 2017);
        $subscription->period_end = (int) mktime(23, 59, 59, 1, 31, 2017);
        $subscription->renews_next = (int) mktime(0, 0, 0, 2, 1, 2017);
        $subscription->prorate = true;
        $subInvoice = new SubscriptionInvoice($subscription);

        // prorated quantity should be qty * 30 days / 62 days
        $proratedQuantity = round(10.0 * 30 / 62.0, 4);

        $expected = [
            'subscription_id' => 100,
            'customer' => self::$customer->id(),
            'ship_to' => null,
            'name' => 'Starter',
            'currency' => 'usd',
            'date' => mktime(0, 0, 0, 2, 1, 2017),
            'items' => [
                [
                    'plan_id' => self::$plan->internal_id,
                    'plan' => 'starter',
                    'type' => 'plan',
                    'name' => 'Starter',
                    'subscription_id' => 100,
                    'period_start' => mktime(0, 0, 0, 1, 2, 2017),
                    'period_end' => mktime(0, 0, 0, 2, 1, 2017) - 1,
                    'prorated' => true,
                    'description' => 'TD',
                    'quantity' => $proratedQuantity,
                    'unit_cost' => 100,
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'taxes' => [
                [
                    'tax_rate' => self::$taxRate->toArray(),
                ],
            ],
            'notes' => 'Notes',
            'metadata' => (object) ['account-rep' => 'Jan'],
            'draft' => false,
        ];

        $this->assertEquals($expected, $subInvoice->getInvoiceParameters(true, true));
    }

    public function testGetInvoiceParametersWithDiscount(): void
    {
        $subscription = $this->getSubscription();
        $redemption = new CouponRedemption();
        $redemption->coupon = self::$coupon->id;
        $redemption->coupon_id = (int) self::$coupon->id();
        $redemptions = [$redemption];
        $subscription->setCouponRedemptions($redemptions);
        $subInvoice = new SubscriptionInvoice($subscription);

        $expected = [
            'subscription_id' => 100,
            'customer' => self::$customer->id(),
            'ship_to' => null,
            'name' => 'Starter',
            'currency' => 'usd',
            'date' => self::$startOfToday,
            'items' => [
                [
                    'plan_id' => self::$plan->internal_id,
                    'plan' => 'starter',
                    'type' => 'plan',
                    'name' => 'Starter',
                    'description' => 'TD',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 10,
                    'unit_cost' => 100,
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [
                [
                    'coupon' => self::$coupon->toArray(),
                    'rate' => self::$coupon->id,
                    'rate_id' => self::$coupon->id(),
                ],
            ],
            'taxes' => [
                [
                    'tax_rate' => self::$taxRate->toArray(),
                ],
            ],
            'notes' => 'Notes',
            'metadata' => (object) ['account-rep' => 'Jan'],
            'draft' => false,
        ];

        $this->assertEquals($expected, $subInvoice->getInvoiceParameters(true, true));
    }

    public function testGetInvoiceParametersWithProratedDollarDiscount(): void
    {
        $subscription = $this->getSubscription();
        $subscription->snap_to_nth_day = 1;
        $subscription->start_date = (int) mktime(0, 0, 0, 1, 2, 2017);
        $subscription->period_start = (int) mktime(0, 0, 0, 1, 2, 2017);
        $subscription->period_end = (int) mktime(23, 59, 59, 1, 31, 2017);
        $subscription->renews_next = (int) mktime(0, 0, 0, 2, 1, 2017);
        $subscription->prorate = true;

        $coupon = new Coupon();
        $coupon->create([
            'id' => 'coupon2',
            'name' => 'Coupon',
            'value' => 5,
            'is_percent' => 0,
        ]);

        $redemption = new CouponRedemption();
        $redemption->coupon = $coupon->id;
        $redemption->coupon_id = (int) $coupon->id();
        $redemptions = [$redemption];
        $subscription->setCouponRedemptions($redemptions);

        $subInvoice = new SubscriptionInvoice($subscription);

        // prorated quantity should be qty * 30 days / 62 days
        $proratedQuantity = round(10.0 * 30 / 62.0, 4);

        $expected = [
            'subscription_id' => 100,
            'customer' => self::$customer->id(),
            'ship_to' => null,
            'name' => 'Starter',
            'currency' => 'usd',
            'date' => mktime(0, 0, 0, 2, 1, 2017),
            'items' => [
                [
                    'plan_id' => self::$plan->internal_id,
                    'plan' => 'starter',
                    'type' => 'plan',
                    'name' => 'Starter',
                    'subscription_id' => 100,
                    'period_start' => mktime(0, 0, 0, 1, 2, 2017),
                    'period_end' => mktime(0, 0, 0, 2, 1, 2017) - 1,
                    'prorated' => true,
                    'description' => 'TD',
                    'quantity' => $proratedQuantity,
                    'unit_cost' => 100,
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [
                [
                    'amount' => round($coupon->value * 30 / 62.0, 4),
                ],
            ],
            'taxes' => [
                [
                    'tax_rate' => self::$taxRate->toArray(),
                ],
            ],
            'notes' => 'Notes',
            'metadata' => (object) ['account-rep' => 'Jan'],
            'draft' => false,
        ];

        $this->assertEquals($expected, $subInvoice->getInvoiceParameters(true, true));
    }

    public function testGetInvoiceParametersTieredPricing(): void
    {
        $subscription = $this->getSubscription();
        $subscription->quantity = 101;
        $subscription->setPlan(self::$tieredPlan);
        $subInvoice = new SubscriptionInvoice($subscription);

        $expected = [
            'subscription_id' => 100,
            'customer' => self::$customer->id(),
            'ship_to' => null,
            'name' => 'Tiered',
            'currency' => 'usd',
            'date' => self::$startOfToday,
            'items' => [
                [
                    'plan_id' => self::$tieredPlan->internal_id,
                    'plan' => 'tiered',
                    'type' => 'plan',
                    'name' => 'Tiered',
                    'description' => '0 - 50 tier',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 50,
                    'unit_cost' => 100,
                    'metadata' => new stdClass(),
                ],
                [
                    'plan_id' => self::$tieredPlan->internal_id,
                    'plan' => 'tiered',
                    'type' => 'plan',
                    'name' => 'Tiered',
                    'description' => '51 - 100 tier',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 50,
                    'unit_cost' => 80,
                    'metadata' => new stdClass(),
                ],
                [
                    'plan_id' => self::$tieredPlan->internal_id,
                    'plan' => 'tiered',
                    'type' => 'plan',
                    'name' => 'Tiered',
                    'description' => '101+ tier',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 1,
                    'unit_cost' => 70,
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'taxes' => [
                [
                    'tax_rate' => self::$taxRate->toArray(),
                ],
            ],
            'notes' => null,
            'metadata' => (object) ['account-rep' => 'Jan'],
            'draft' => false,
        ];

        $this->assertEquals($expected, $subInvoice->getInvoiceParameters(true, true));
    }

    public function testGetInvoiceParametersVolumePricing(): void
    {
        $subscription = $this->getSubscription();
        $subscription->quantity = 101;
        $subscription->setPlan(self::$volumePlan);
        $subInvoice = new SubscriptionInvoice($subscription);

        $expected = [
            'subscription_id' => 100,
            'customer' => self::$customer->id(),
            'ship_to' => null,
            'name' => 'Volume',
            'currency' => 'usd',
            'date' => self::$startOfToday,
            'items' => [
                [
                    'plan_id' => self::$volumePlan->internal_id,
                    'plan' => 'volume',
                    'type' => 'plan',
                    'name' => 'Volume',
                    'description' => '101+ tier',
                    'subscription_id' => 100,
                    'period_start' => self::$startOfToday,
                    'period_end' => strtotime('+2 months', self::$startOfToday) - 1,
                    'prorated' => false,
                    'quantity' => 101,
                    'unit_cost' => 70,
                    'metadata' => new stdClass(),
                ],
            ],
            'discounts' => [],
            'taxes' => [
                [
                    'tax_rate' => self::$taxRate->toArray(),
                ],
            ],
            'notes' => null,
            'metadata' => (object) ['account-rep' => 'Jan'],
            'draft' => false,
        ];

        $this->assertEquals($expected, $subInvoice->getInvoiceParameters(true, true));
    }

    public function testBuild(): void
    {
        self::$company->subscription_billing_settings->subscription_draft_invoices = true;
        self::$company->subscription_billing_settings->saveOrFail();

        $subInvoice = $this->getSubscriptionInvoice();

        $invoice = $subInvoice->buildWithTaxPreview();

        $this->assertInstanceOf(Invoice::class, $invoice);
        // test that a couple of parameters were set
        $this->assertEquals(100, $invoice->subscription_id);
        $this->assertEquals(self::$startOfToday, $invoice->date);
        $this->assertTrue($invoice->draft);

        self::$company->subscription_billing_settings->subscription_draft_invoices = false;
        self::$company->subscription_billing_settings->saveOrFail();

        $subInvoice = $this->getSubscriptionInvoice();

        $invoice = $subInvoice->buildWithTaxPreview();

        $this->assertInstanceOf(Invoice::class, $invoice);
        // test that a couple of parameters were set
        $this->assertEquals(100, $invoice->subscription_id);
        $this->assertEquals(self::$startOfToday, $invoice->date);
        $this->assertFalse($invoice->draft);
    }

    public function testBuildWithPaymentSource(): void
    {
        $card = new Card();
        $subscription = $this->getSubscription();
        $subscription->setPaymentSource($card);
        $subInvoice = new SubscriptionInvoice($subscription);

        $invoice = $subInvoice->buildWithTaxPreview();

        $this->assertInstanceOf(Invoice::class, $invoice);
        // test that a couple of parameters were set
        $this->assertEquals(100, $invoice->subscription_id);
        $this->assertEquals(self::$startOfToday, $invoice->date);
        $this->assertEquals($card, $invoice->payment_source);
    }

    public function testGetRecurringTotal(): void
    {
        $subInvoice = $this->getSubscriptionInvoice();

        $total = $subInvoice->getRecurringTotal();

        $this->assertInstanceOf(Money::class, $total);
        $this->assertEquals('usd', $total->currency);
        $this->assertEquals(105000, $total->amount);
    }

    public function testIsProrated(): void
    {
        $subscription = $this->getSubscription();
        $subInvoice = new SubscriptionInvoice($subscription);

        $subscription->snap_to_nth_day = 1;
        $subscription->prorate = false;
        $this->assertFalse($subInvoice->isProrated());

        $subscription->prorate = true;
        $subscription->renewed_last = time();
        $this->assertFalse($subInvoice->isProrated());

        $subscription->renewed_last = null;
        $subscription->snap_to_nth_day = null;
        $this->assertFalse($subInvoice->isProrated());

        $subscription->snap_to_nth_day = 4;
        $this->assertTrue($subInvoice->isProrated(new CarbonImmutable('2016-03-02')));

        // should not prorate if it's already the nth day of the month
        $subscription->snap_to_nth_day = 1;
        $this->assertFalse($subInvoice->isProrated(new CarbonImmutable('2017-01-01')));
    }

    public function testGetSalesTaxAddress(): void
    {
        $subscription = $this->getSubscription();
        $subInvoice = new SubscriptionInvoice($subscription);

        $address = $subInvoice->getSalesTaxAddress();
        $this->assertEquals('Test', $address->getAddressLine1());
        $this->assertEquals('Address', $address->getAddressLine2());
        $this->assertEquals('City', $address->getLocality());
        $this->assertEquals('State', $address->getAdministrativeArea());
        $this->assertEquals('00000', $address->getPostalCode());
        $this->assertEquals('CA', $address->getCountryCode());

        $shipTo = new ShippingDetail();
        $shipTo->name = 'Test';
        $shipTo->address1 = '1234 main st';
        $shipTo->city = 'Austin';
        $shipTo->state = 'TX';
        $shipTo->postal_code = '78701';
        $shipTo->country = 'US';
        $subscription->ship_to = $shipTo;

        $address = $subInvoice->getSalesTaxAddress();
        $this->assertEquals('1234 main st', $address->getAddressLine1());
        $this->assertEquals(null, $address->getAddressLine2());
        $this->assertEquals('Austin', $address->getLocality());
        $this->assertEquals('TX', $address->getAdministrativeArea());
        $this->assertEquals('78701', $address->getPostalCode());
        $this->assertEquals('US', $address->getCountryCode());
    }

    private function getSubscriptionInvoice(): SubscriptionInvoice
    {
        return new SubscriptionInvoice($this->getSubscription());
    }

    private function getSubscription(): Subscription
    {
        $sub = new Subscription(['id' => 100]);
        $sub->tenant_id = (int) self::$company->id();
        $sub->setCustomer(self::$customer);
        $sub->setPlan(self::$plan);
        $sub->start_date = self::$startOfToday;
        $sub->period_start = self::$startOfToday;
        $sub->period_end = strtotime('+2 months', self::$startOfToday);
        if (0 == $sub->period_end % 2) {
            --$sub->period_end;
        }
        $sub->renews_next = self::$startOfToday;
        $sub->quantity = 10;
        $sub->metadata = (object) ['account-rep' => 'Jan'];
        $sub->setPlan(self::$plan);
        $sub->plan_id = self::$plan->internal_id;

        return $sub;
    }

    public function testDiscountsAndTaxes(): void
    {
        self::$company->accounts_receivable_settings->tax_calculator = 'avalara';
        self::$company->accounts_receivable_settings->saveOrFail();
        self::hasAvalaraAccount();
        $instance = TaxCalculatorFactoryFacade::$instance;

        $subscription = new Subscription(['id' => 101]);
        $subscription->tenant_id = (int) self::$company->id();
        $subscription->setCustomer(self::$customer);
        self::$plan->amount = 200;
        $subscription->setPlan(self::$plan);

        $subscription = $this->getSubscription();
        $redemption = new CouponRedemption();
        $redemption->coupon = self::$coupon->id;
        $redemption->coupon_id = (int) self::$coupon->id();
        $redemptions = [$redemption];
        $subscription->setCouponRedemptions($redemptions);

        $taxCalc = Mockery::mock(AvalaraTaxCalculator::class);
        $taxCalculatorFactory = new TaxCalculatorFactory($taxCalc, Mockery::mock(InvoicedTaxCalculator::class));
        TaxCalculatorFactoryFacade::$instance = new TaxCalculatorFactoryFacade($taxCalculatorFactory);

        $subInvoice = new SubscriptionInvoice($subscription);
        $taxCalc->shouldReceive('assess')
            ->withArgs(function ($arg) {
                return 1000 === $arg->getDiscounts();
            })->once();
        $subInvoice->getInvoiceParameters(true, true);

        $coupon = new Coupon();
        $coupon->id = 'coupon4';
        $coupon->name = 'Coupon4';
        $coupon->value = 5;
        $coupon->is_percent = false;
        $coupon->saveOrFail();
        $redemption = new CouponRedemption();
        $redemption->coupon = $coupon->id;
        $redemption->coupon_id = (int) $coupon->id();
        $redemptions = [$redemption];
        $subscription->setCouponRedemptions($redemptions);
        $subInvoice = new SubscriptionInvoice($subscription);
        $taxCalc->shouldReceive('assess')
            ->withArgs(function ($arg) {
                return 500 === $arg->getDiscounts();
            })->once();
        $subInvoice->getInvoiceParameters(true, true);
        TaxCalculatorFactoryFacade::$instance = $instance;
    }
}
