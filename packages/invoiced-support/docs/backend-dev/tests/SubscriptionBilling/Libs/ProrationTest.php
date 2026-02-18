<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\AccountsReceivable\Models\Item;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\Libs\Proration;
use App\SubscriptionBilling\Models\PendingLineItem;
use App\SubscriptionBilling\Models\Plan;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Models\SubscriptionAddon;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ProrationTest extends AppTestCase
{
    private static Plan $starterPlan;
    private static Plan $proPlan;
    private static Plan $starterPlanYearly;
    private static Plan $customPlan1;
    private static Plan $customPlan2;
    private static SubscriptionAddon $addon1;
    private static SubscriptionAddon $addon2;
    private static SubscriptionAddon $addon3;
    private static CarbonImmutable $startDate;
    private static CarbonImmutable $endDate;
    private static CarbonImmutable $currentTime;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        date_default_timezone_set('UTC');
        self::hasCompany();
        self::hasCustomer();

        self::$starterPlan = new Plan();
        self::$starterPlan->tenant_id = (int) self::$company->id();
        self::$starterPlan->id = 'starter';
        self::$starterPlan->internal_id = 1;
        self::$starterPlan->name = 'Starter';
        self::$starterPlan->description = 'Our most basic plan';
        self::$starterPlan->interval = Interval::MONTH;
        self::$starterPlan->interval_count = 1;
        self::$starterPlan->currency = 'usd';
        self::$starterPlan->amount = 100;
        Plan::setCurrent(self::$starterPlan);

        self::$proPlan = new Plan();
        self::$proPlan->tenant_id = (int) self::$company->id();
        self::$proPlan->id = 'pro';
        self::$proPlan->internal_id = 2;
        self::$proPlan->name = 'Pro';
        self::$proPlan->description = 'For professionals';
        self::$proPlan->interval = Interval::MONTH;
        self::$proPlan->interval_count = 1;
        self::$proPlan->currency = 'usd';
        self::$proPlan->amount = 150;
        Plan::setCurrent(self::$proPlan);

        self::$starterPlanYearly = new Plan();
        self::$starterPlanYearly->tenant_id = (int) self::$company->id();
        self::$starterPlanYearly->id = 'starter-annual';
        self::$starterPlanYearly->internal_id = 3;
        self::$starterPlanYearly->name = 'Starter';
        self::$starterPlanYearly->description = 'Pay yearly!';
        self::$starterPlanYearly->interval = Interval::YEAR;
        self::$starterPlanYearly->interval_count = 1;
        self::$starterPlanYearly->currency = 'usd';
        self::$starterPlanYearly->amount = 1200;
        Plan::setCurrent(self::$starterPlanYearly);

        self::$customPlan1 = new Plan();
        self::$customPlan1->id = 'custom-plan-1';
        self::$customPlan1->name = 'Custom Plan 1';
        self::$customPlan1->pricing_mode = Plan::PRICING_CUSTOM;
        self::$customPlan1->interval = Interval::MONTH;
        self::$customPlan1->interval_count = 1;
        self::$customPlan1->saveOrFail(); // test cases require persistence

        self::$customPlan2 = new Plan();
        self::$customPlan2->id = 'custom-plan-2';
        self::$customPlan2->name = 'Custom Plan 2';
        self::$customPlan2->pricing_mode = Plan::PRICING_CUSTOM;
        self::$customPlan2->interval = Interval::MONTH;
        self::$customPlan2->interval_count = 2;
        self::$customPlan2->saveOrFail(); // test cases require persistence

        self::$item = new Item();
        self::$item->name = 'Widget';
        self::$item->id = 'widget';
        self::$item->internal_id = 4;
        self::$item->currency = 'usd';
        self::$item->unit_cost = 49;
        Item::setCurrent(self::$item);

        self::$item = new Item();
        self::$item->name = 'Another Widget';
        self::$item->id = 'widget2';
        self::$item->internal_id = 5;
        self::$item->currency = 'usd';
        self::$item->unit_cost = 59;
        Item::setCurrent(self::$item);

        self::$addon1 = new SubscriptionAddon(['id' => 1]);
        self::$addon1->catalog_item = 'widget';
        self::$addon1->quantity = 1;

        self::$addon2 = new SubscriptionAddon(['id' => 1]);
        self::$addon2->catalog_item = 'widget';
        self::$addon2->quantity = 3;

        self::$addon3 = new SubscriptionAddon(['id' => 3]);
        self::$addon3->catalog_item = 'widget2';
        self::$addon3->quantity = 4;

        // fabricate an end date such that it makes the current
        // time 2/3 of the way through the current billing cycle
        self::$startDate = new CarbonImmutable('1970-01-15T13:46:40Z');
        self::$endDate = self::$startDate->modify('+1 month');
        self::$currentTime = new CarbonImmutable('1970-02-05T05:46:40Z');
    }

    private function buildSubscription(): Subscription
    {
        $subscription = new Subscription(['id' => 100]);
        $subscription->tenant_id = (int) self::$company->id();
        $subscription->setCustomer(self::$customer);
        $subscription->setPlan(self::$starterPlan);
        $subscription->start_date = self::$startDate->getTimestamp();
        $subscription->period_start = self::$startDate->getTimestamp();
        $subscription->period_end = self::$endDate->getTimestamp() - 1;
        $subscription->quantity = 1;

        return $subscription;
    }

    public function testGetTotal(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';

        $proration = new Proration($before, $after, self::$currentTime);

        $total = $proration->getTotal();

        $this->assertInstanceOf(Money::class, $total);
        $this->assertEquals('usd', $total->currency);
        $this->assertEquals(1667, $total->amount);
    }

    public function testApplyDoNothing(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';

        $proration = new Proration($before, $after, self::$currentTime);

        // apply the proration (should do nothing)
        $this->assertFalse($proration->apply());
    }

    public function testApplyTrialing(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';
        $after->status = SubscriptionStatus::TRIALING;

        $proration = new Proration($before, $after, self::$currentTime);

        // apply the proration (should do nothing)
        $this->assertFalse($proration->apply());
    }

    public function testApplyFinished(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';
        $after->status = SubscriptionStatus::FINISHED;

        $proration = new Proration($before, $after, self::$currentTime);

        // apply the proration (should do nothing)
        $this->assertFalse($proration->apply());
    }

    public function testApplyCanceled(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';
        $after->status = SubscriptionStatus::CANCELED;

        $proration = new Proration($before, $after, self::$currentTime);

        // apply the proration (should do nothing)
        $this->assertFalse($proration->apply());
    }

    public function testApply(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';

        $proration = new Proration($before, $after, self::$currentTime);

        $this->assertTrue($proration->apply());

        // should create 2 pending line items
        $pendingLineItems = PendingLineItem::where('customer_id', self::$customer->id())
            ->sort('id desc')
            ->first(2);

        $this->assertCount(2, $pendingLineItems);
    }

    public function testBuildPendingLineItems(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';

        $proration = new Proration($before, $after, self::$currentTime);

        // build pending line items for the proration
        $lines = $proration->buildPendingLineItems();
        $this->assertCount(2, $lines);
        $this->assertInstanceOf(PendingLineItem::class, $lines[0]);
        foreach ($lines as $line) {
            $this->assertInstanceOf(PendingLineItem::class, $line);
            $this->assertEquals(self::$customer->id(), $line->customer_id);
        }
    }

    public function testChangedCycle(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $proration = new Proration($before, $before, self::$currentTime);

        $this->assertFalse($proration->changedCycle());

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter-annual';

        $proration = new Proration($before, $after, self::$currentTime);

        $this->assertTrue($proration->changedCycle());
    }

    public function testChangedPlan(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $proration = new Proration($before, $before, self::$currentTime);

        $this->assertFalse($proration->changedPlan());

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';

        $proration = new Proration($before, $after, self::$currentTime);

        $this->assertTrue($proration->changedPlan());
    }

    public function testChangedQuantity(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $proration = new Proration($before, $before, self::$currentTime);

        $this->assertFalse($proration->changedQuantity());

        $after = $this->buildSubscription();
        $after->quantity = 2;
        $after->plan = 'starter';

        $proration = new Proration($before, $after, self::$currentTime);

        $this->assertTrue($proration->changedQuantity());
    }

    public function testChangedAmount(): void
    {
        $before = $this->buildSubscription();
        $before->amount = 1;
        $before->start_date = self::$startDate->getTimestamp();

        $proration = new Proration($before, $before, self::$currentTime);

        $this->assertFalse($proration->changedQuantity());

        $after = $this->buildSubscription();
        $after->amount = 2;

        $proration = new Proration($before, $after, self::$currentTime);

        $this->assertTrue($proration->changedAmount());
    }

    public function testChangedAmountAndQuantity(): void
    {
        $before = $this->buildSubscription();
        $before->amount = 1;
        $before->quantity = 1;
        $before->start_date = self::$startDate->getTimestamp();

        $proration = new Proration($before, $before, self::$currentTime);

        $this->assertFalse($proration->changedQuantity());

        $after = $this->buildSubscription();
        $after->quantity = 2;
        $after->amount = 2;

        $proration = new Proration($before, $after, self::$currentTime);

        $this->assertTrue($proration->changedAmount());
        $this->assertTrue($proration->changedQuantity());
    }

    public function testChangedAddons(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $proration = new Proration($before, $before, self::$currentTime);

        $this->assertFalse($proration->changedAddons());

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';
        $after->setAddons([self::$addon1]);

        $proration = new Proration($before, $after, self::$currentTime);

        $this->assertTrue($proration->changedAddons());
    }

    public function testChangeNothing(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $proration = new Proration($before, $before, self::$currentTime);

        $this->assertEquals([], $proration->getLines());
    }

    public function testChangeNothingAddons(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->setAddons([self::$addon1, self::$addon2, self::$addon3]);
        $before->start_date = self::$startDate->getTimestamp();

        $proration = new Proration($before, $before, self::$currentTime);
        $this->assertEquals([], $proration->getLines());
    }

    //
    // Changing Plan Prorations
    //

    public function testUpgradePlan(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 2;
        $after->plan = 'pro';

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'starter',
                'plan_id' => 1,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Starter',
                'description' => "(removed 1)\nOur most basic plan",
                'quantity' => -0.3333,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
            ],
            [
                'type' => 'plan',
                'plan' => 'pro',
                'plan_id' => 2,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Pro',
                'description' => "(added 2)\nFor professionals",
                'quantity' => 0.6667,
                'unit_cost' => 150,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testDowngradePlan(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 2;
        $before->plan = 'pro';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'pro',
                'plan_id' => 2,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Pro',
                'description' => "(removed 2)\nFor professionals",
                'quantity' => -0.6667,
                'unit_cost' => 150,
                'metadata' => new \stdClass(),
            ],
            [
                'type' => 'plan',
                'plan' => 'starter',
                'plan_id' => 1,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Starter',
                'description' => "(added 1)\nOur most basic plan",
                'quantity' => 0.3333,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testChangePlanDifferentBillingCycle(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 2;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter-annual';

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'starter',
                'plan_id' => 1,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Starter',
                'description' => "(removed 2)\nOur most basic plan",
                'quantity' => -0.6667,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    //
    // Quantity Prorations
    //

    public function testDowngradeQuantity(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 2;
        $before->plan = 'pro';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'pro',
                'plan_id' => 2,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Pro',
                'description' => "(removed 1)\nFor professionals",
                'quantity' => -0.3333,
                'unit_cost' => 150,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testUpgradeQuantity(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'pro';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 2;
        $after->plan = 'pro';

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'pro',
                'plan_id' => 2,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Pro',
                'description' => "(added 1)\nFor professionals",
                'quantity' => 0.3333,
                'unit_cost' => 150,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testChangeQuantityDifferentBillingCycle(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 2;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter-annual';

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'starter',
                'plan_id' => 1,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Starter',
                'description' => "(removed 2)\nOur most basic plan",
                'quantity' => -0.6667,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testDecreaseAmount(): void
    {
        $before = $this->buildSubscription();
        $before->setPlan(self::$customPlan1);
        $before->quantity = 1;
        $before->amount = 20;
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->setPlan(self::$customPlan1);
        $after->quantity = 1;
        $after->amount = 10;

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(decreased price)',
                'quantity' => 0.3333,
                'unit_cost' => -10,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testDecreaseAmountAndQuantity(): void
    {
        $before = $this->buildSubscription();
        $before->setPlan(self::$customPlan1);
        $before->quantity = 2;
        $before->amount = 20;
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->setPlan(self::$customPlan1);
        $after->quantity = 1;
        $after->amount = 10;

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(removed 2)',
                'quantity' => -0.6667,
                'unit_cost' => 20,
                'metadata' => new \stdClass(),
            ],
            [
                'type' => 'plan',
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(added 1)',
                'quantity' => 0.3333,
                'unit_cost' => 10,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testIncreaseAmount(): void
    {
        $before = $this->buildSubscription();
        $before->setPlan(self::$customPlan1);
        $before->quantity = 1;
        $before->amount = 10;
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->setPlan(self::$customPlan1);
        $after->quantity = 1;
        $after->amount = 20;

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(increased price)',
                'quantity' => 0.3333,
                'unit_cost' => 10,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testIncreaseAmountAndQuantity(): void
    {
        $before = $this->buildSubscription();
        $before->setPlan(self::$customPlan1);
        $before->quantity = 1;
        $before->amount = 10;
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->setPlan(self::$customPlan1);
        $after->quantity = 2;
        $after->amount = 20;

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(removed 1)',
                'quantity' => -0.3333,
                'unit_cost' => 10,
                'metadata' => new \stdClass(),
            ],
            [
                'type' => 'plan',
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(added 2)',
                'quantity' => 0.6667,
                'unit_cost' => 20,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testChangeAmountDifferentBillingCycle(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->amount = 20;
        $before->setPlan(self::$customPlan1);
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->amount = 10;
        $after->setPlan(self::$customPlan2);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(removed 1)',
                'quantity' => -0.3333,
                'unit_cost' => 20,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    //
    // Addon Prorations
    //

    public function testAddedAddon(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';
        $after->setAddons([self::$addon1]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => null,
                'catalog_item' => 'widget',
                'catalog_item_id' => 4,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Widget',
                'description' => '(added 1)',
                'quantity' => 0.3333,
                'unit_cost' => 49,
                'discountable' => true,
                'taxable' => true,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testRemovedAddon(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([self::$addon1]);

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => null,
                'catalog_item' => 'widget',
                'catalog_item_id' => 4,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Widget',
                'description' => '(removed 1)',
                'quantity' => -0.3333,
                'unit_cost' => 49,
                'discountable' => true,
                'taxable' => true,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testDowngradeAddonQuantity(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([self::$addon2]);

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';
        $after->setAddons([self::$addon1]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => null,
                'catalog_item' => 'widget',
                'catalog_item_id' => 4,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Widget',
                'description' => '(removed 2)',
                'quantity' => -0.6667,
                'unit_cost' => 49,
                'discountable' => true,
                'taxable' => true,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testUpgradeAddonQuantity(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([self::$addon1]);

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';
        $after->setAddons([self::$addon2]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => null,
                'catalog_item' => 'widget',
                'catalog_item_id' => 4,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Widget',
                'description' => '(added 2)',
                'quantity' => 0.6667,
                'unit_cost' => 49,
                'discountable' => true,
                'taxable' => true,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testDecreaseAddonAmount(): void
    {
        $addonBefore = new SubscriptionAddon(['id' => 4]);
        $addonBefore->amount = 20;
        $addonBefore->setPlan(self::$customPlan1);
        $addonBefore->quantity = 1;

        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([$addonBefore]);

        $addonAfter = new SubscriptionAddon(['id' => 4]);
        $addonAfter->amount = 10;
        $addonAfter->setPlan(self::$customPlan1);
        $addonAfter->quantity = 1;

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';
        $after->setAddons([$addonAfter]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'subscription_id' => 100,
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(decreased price)',
                'quantity' => 0.3333,
                'unit_cost' => -10,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testDecreaseAddonAmountAndQuantity(): void
    {
        $addonBefore = new SubscriptionAddon(['id' => 4]);
        $addonBefore->amount = 20;
        $addonBefore->setPlan(self::$customPlan1);
        $addonBefore->quantity = 2;

        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([$addonBefore]);

        $addonAfter = new SubscriptionAddon(['id' => 4]);
        $addonAfter->amount = 10;
        $addonAfter->setPlan(self::$customPlan1);
        $addonAfter->quantity = 1;

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';
        $after->setAddons([$addonAfter]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'subscription_id' => 100,
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(removed 2)',
                'quantity' => -0.6667,
                'unit_cost' => 20,
                'metadata' => new \stdClass(),
            ],
            [
                'type' => 'plan',
                'subscription_id' => 100,
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(added 1)',
                'quantity' => 0.3333,
                'unit_cost' => 10,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testIncreaseAddonAmount(): void
    {
        $addonBefore = new SubscriptionAddon(['id' => 4]);
        $addonBefore->amount = 10;
        $addonBefore->setPlan(self::$customPlan1);
        $addonBefore->quantity = 1;

        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([$addonBefore]);

        $addonAfter = new SubscriptionAddon(['id' => 4]);
        $addonAfter->amount = 20;
        $addonAfter->setPlan(self::$customPlan1);
        $addonAfter->quantity = 1;

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';
        $after->setAddons([$addonAfter]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'subscription_id' => 100,
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(increased price)',
                'quantity' => 0.3333,
                'unit_cost' => 10,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testIncreaseAddonAmountAndQuantity(): void
    {
        $addonBefore = new SubscriptionAddon(['id' => 4]);
        $addonBefore->amount = 10;
        $addonBefore->setPlan(self::$customPlan1);
        $addonBefore->quantity = 1;

        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([$addonBefore]);

        $addonAfter = new SubscriptionAddon(['id' => 4]);
        $addonAfter->amount = 20;
        $addonAfter->setPlan(self::$customPlan1);
        $addonAfter->quantity = 2;

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter';
        $after->setAddons([$addonAfter]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            [
                'type' => 'plan',
                'subscription_id' => 100,
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(removed 1)',
                'quantity' => -0.3333,
                'unit_cost' => 10,
                'metadata' => new \stdClass(),
            ],
            [
                'type' => 'plan',
                'subscription_id' => 100,
                'plan' => 'custom-plan-1',
                'plan_id' => self::$customPlan1->internal_id,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Custom Plan 1',
                'description' => '(added 2)',
                'quantity' => 0.6667,
                'unit_cost' => 20,
                'metadata' => new \stdClass(),
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testMultipleChanges(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([self::$addon1]);

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'pro';
        $after->setAddons([self::$addon3]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            // prorations for switching plan
            [
                'type' => 'plan',
                'plan' => 'starter',
                'plan_id' => 1,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Starter',
                'description' => "(removed 1)\nOur most basic plan",
                'quantity' => -0.3333,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
            ],
            [
                'type' => 'plan',
                'plan' => 'pro',
                'plan_id' => 2,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Pro',
                'description' => "(added 1)\nFor professionals",
                'quantity' => 0.3333,
                'unit_cost' => 150,
                'metadata' => new \stdClass(),
            ],
            // credit for removing widget
            [
                'type' => null,
                'catalog_item' => 'widget',
                'catalog_item_id' => 4,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Widget',
                'description' => '(removed 1)',
                'quantity' => -0.3333,
                'unit_cost' => 49,
                'discountable' => true,
                'taxable' => true,
                'taxes' => [],
            ],
            // charge for adding widget2
            [
                'type' => null,
                'catalog_item' => 'widget2',
                'catalog_item_id' => 5,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Another Widget',
                'description' => '(added 4)',
                'quantity' => 1.3333,
                'unit_cost' => 59,
                'discountable' => true,
                'taxable' => true,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testAddonChangeDifferentBillingCycle(): void
    {
        $before = $this->buildSubscription();
        $before->quantity = 1;
        $before->plan = 'starter';
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([self::$addon1]);

        $after = $this->buildSubscription();
        $after->quantity = 1;
        $after->plan = 'starter-annual';
        $after->setAddons([self::$addon2]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            // should prorate the plan
            [
                'type' => 'plan',
                'plan' => 'starter',
                'plan_id' => 1,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Starter',
                'description' => "(removed 1)\nOur most basic plan",
                'quantity' => -0.3333,
                'unit_cost' => 100,
                'metadata' => new \stdClass(),
            ],
            // should credit unused time for widget
            [
                'type' => null,
                'catalog_item' => 'widget',
                'catalog_item_id' => 4,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Widget',
                'description' => '(removed 1)',
                'quantity' => -0.3333,
                'unit_cost' => 49,
                'discountable' => true,
                'taxable' => true,
                'taxes' => [],
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }

    public function testInvd2035(): void
    {
        $addon1 = new SubscriptionAddon(['quantity' => 1]);
        $addon1->setPlan(self::$starterPlan);

        $addon2 = new SubscriptionAddon(['quantity' => 1]);
        $addon2->setPlan(self::$starterPlan);

        $addon3 = new SubscriptionAddon(['quantity' => 1]);
        $addon3->setPlan(self::$starterPlan);

        $addon4 = new SubscriptionAddon(['quantity' => 1]);
        $addon4->setPlan(self::$starterPlan);

        $before = $this->buildSubscription();
        $before->start_date = self::$startDate->getTimestamp();
        $before->setAddons([$addon1, $addon2, $addon3, $addon4]);

        $after = $this->buildSubscription();
        $after->setAddons([$addon1, $addon2, $addon3]);

        $proration = new Proration($before, $after, self::$currentTime);

        $expected = [
            // should credit unused time for the removed addon
            [
                'type' => 'plan',
                'plan' => 'starter',
                'plan_id' => 1,
                'subscription_id' => 100,
                'period_start' => self::$currentTime->getTimestamp(),
                'period_end' => self::$endDate->getTimestamp() - 1,
                'prorated' => true,
                'name' => 'Starter',
                'description' => "(removed 1)\nOur most basic plan",
                'quantity' => -0.3333,
                'unit_cost' => 100,
                'metadata' => (object) [],
            ],
        ];
        $this->assertEquals($expected, $proration->getLines());
    }
}
