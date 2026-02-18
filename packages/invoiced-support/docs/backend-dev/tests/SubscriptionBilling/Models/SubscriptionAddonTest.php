<?php

namespace App\Tests\SubscriptionBilling\Models;

use App\SubscriptionBilling\Models\Plan;
use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\Models\SubscriptionAddon;
use App\Tests\AppTestCase;
use stdClass;

class SubscriptionAddonTest extends AppTestCase
{
    private static SubscriptionAddon $addon;
    private static SubscriptionAddon $addon2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasPlan();
        self::hasSubscription();
        self::hasItem();
    }

    public function testCreate(): void
    {
        self::$addon = new SubscriptionAddon();
        self::$addon->subscription_id = (int) self::$subscription->id();
        self::$addon->catalog_item = self::$item->id;
        $this->assertTrue(self::$addon->save());
        $this->assertEquals(self::$item->internal_id, self::$addon->catalog_item_id);

        $this->assertEquals(self::$company->id(), self::$addon->tenant_id);
        $this->assertEquals(self::$subscription->id(), self::$addon->subscription_id);

        self::$addon2 = new SubscriptionAddon();
        self::$addon2->subscription_id = (int) self::$subscription->id();
        self::$addon2->setPlan(self::$plan);
        $this->assertTrue(self::$addon2->save());
        $this->assertEquals(self::$plan->internal_id, self::$addon2->plan_id);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$addon->quantity = 100;
        $this->assertTrue(self::$addon->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$addon->id,
            'object' => 'subscription_addon',
            'catalog_item' => self::$item->id,
            'plan' => null,
            'quantity' => 100,
            'description' => null,
            'amount' => null,
            'created_at' => self::$addon->created_at,
            'updated_at' => self::$addon->updated_at,
        ];

        $this->assertEquals($expected, self::$addon->toArray());
    }

    public function testLineItemsFromCatalogItem(): void
    {
        $expected = [
            [
                'catalog_item' => 'test-item',
                'catalog_item_id' => self::$item->internal_id,
                'name' => 'Test Item',
                'description' => 'Description',
                'type' => null,
                'quantity' => 100,
                'unit_cost' => 1000,
                'discountable' => true,
                'taxable' => true,
                'taxes' => [],
            ],
        ];

        $this->assertEquals($expected, self::$addon->lineItems());
    }

    public function testLineItemFromPlan(): void
    {
        $expected = [
            [
                'plan' => 'starter',
                'plan_id' => self::$plan->internal_id,
                'name' => 'Starter',
                'description' => null,
                'type' => 'plan',
                'quantity' => 1.0,
                'unit_cost' => 100.0,
                'metadata' => new stdClass(),
            ],
        ];

        $this->assertEquals($expected, self::$addon2->lineItems());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$addon->delete());
    }

    /**
     * Assert that subscription addons w/ non-custom plans should have a null amount value.
     */
    public function testNonCustomPlanAmount(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->amount = 1;
        $plan->pricing_mode = Plan::PRICING_PER_UNIT;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $addon = new SubscriptionAddon();
        $addon->setPlan($plan);
        $addon->amount = 20;

        $this->expectExceptionMessage('Amounts are only allowed when the plan has a custom pricing mode');
        $addon->saveOrFail();
    }

    /**
     * Assert that subscription addons w/ custom plans should have a non-null amount value.
     */
    public function testAmountWithCustomPlan(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->pricing_mode = Plan::PRICING_CUSTOM;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $addon = new SubscriptionAddon();
        $addon->setPlan($plan);

        $this->expectExceptionMessage('An amount is required when the subscription has a custom plan');
        $addon->saveOrFail();
    }

    public function testQuantityWithCustomPlan(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->pricing_mode = Plan::PRICING_CUSTOM;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $addon = new SubscriptionAddon();
        $addon->setPlan($plan);
        $addon->subscription_id = self::$subscription->id;
        $addon->amount = 100;
        $addon->quantity = 2;

        $this->assertTrue($addon->save());
    }
}
