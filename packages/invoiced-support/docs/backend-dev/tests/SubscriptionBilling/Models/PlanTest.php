<?php

namespace App\Tests\SubscriptionBilling\Models;

use App\AccountsReceivable\Models\PricingObject;
use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\Models\Plan;
use App\Tests\AppTestCase;
use stdClass;

class PlanTest extends AppTestCase
{
    private static Plan $plan2;
    private static Plan $zeroPlan;
    private static Plan $planInvalidTiers;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasTaxRate();
        self::hasCoupon();
        self::hasItem();
    }

    public function testInterval(): void
    {
        $plan = new Plan();
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 3;
        $interval = $plan->interval();
        $this->assertInstanceOf(Interval::class, $interval);
        $this->assertEquals(3, $interval->count);
        $this->assertEquals('month', $interval->interval);
    }

    public function testCustomerFacingNameTest(): void
    {
        $plan = new Plan();
        $plan->name = 'Hidden';
        $this->assertEquals('Hidden', $plan->getCustomerFacingName());

        $plan->catalog_item = self::$item->id;
        $this->assertEquals('Test Item', $plan->getCustomerFacingName());
    }

    public function testLineItem(): void
    {
        $plan = new Plan();
        $plan->internal_id = 100;
        $plan->id = 'test';
        $plan->name = 'My Plan';
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 2;
        $plan->amount = 39;
        $plan->description = 'description...';
        $plan->metadata = (object) [
            'a' => 'a',
            'b' => 'b',
        ];

        $metadata = new stdClass();
        $metadata->a = 'a';
        $metadata->b = 'b';

        $expected = [
            'type' => 'plan',
            'plan_id' => 100,
            'plan' => 'test',
            'name' => 'My Plan',
            'description' => 'description...',
            'unit_cost' => 39,
            'metadata' => $metadata,
        ];

        $this->assertEquals($expected, $plan->lineItem());
    }

    public function testLineItemWithCatalogItem(): void
    {
        $plan = new Plan();
        $plan->internal_id = 100;
        $plan->id = 'test';
        $plan->name = 'My Plan';
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 2;
        $plan->amount = 39;
        $plan->description = 'description...';
        $plan->catalog_item = self::$item->id;
        $plan->metadata = (object) [
            'a' => 'a',
            'b' => 'b',
        ];

        $metadata = new stdClass();
        $metadata->a = 'a';
        $metadata->b = 'b';

        $expected = [
            'type' => 'plan',
            'plan_id' => 100,
            'plan' => 'test',
            'name' => 'Test Item',
            'description' => 'description...',
            'unit_cost' => 39,
            'catalog_item' => self::$item->id,
            'catalog_item_id' => self::$item->internal_id,
            'metadata' => $metadata,
        ];

        $this->assertEquals($expected, $plan->lineItem());
    }

    public function testCreateInvalidID(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->id = 'AB*#$&)#&%)*#)(%*';
        $plan->amount = 0;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;

        $this->assertFalse($plan->save());
    }

    public function testCreateInvalidAmount(): void
    {
        $plan = new Plan();
        $this->assertFalse($plan->create([
            'name' => 'Test',
            'id' => 'test',
            'amount' => -10,
            'interval' => Interval::MONTH,
            'interval_count' => 1,
        ]));
    }

    public function testCreate(): void
    {
        self::$plan = new Plan();
        $this->assertTrue(self::$plan->create([
            'name' => 'Test',
            'id' => 'test',
            'amount' => 107.305,
            'interval' => Interval::MONTH,
            'interval_count' => 1,
        ]));

        $this->assertEquals(self::$company->id(), self::$plan->tenant_id);

        self::$plan2 = new Plan();
        $this->assertTrue(self::$plan2->create([
            'name' => 'Test 2',
            'id' => 'test-2',
            'amount' => 5,
            'interval' => Interval::MONTH,
            'interval_count' => 1,
        ]));

        self::$zeroPlan = new Plan();
        self::$zeroPlan->name = 'Zero';
        self::$zeroPlan->id = 'zero';
        self::$zeroPlan->amount = 0;
        self::$zeroPlan->interval = Interval::MONTH;
        self::$zeroPlan->interval_count = 1;
        $this->assertTrue(self::$zeroPlan->save());
    }

    /**
     * @depends testCreate
     */
    public function testCreateNonUnique(): void
    {
        $plan = new Plan();
        $errors = $plan->getErrors();

        $plan->name = 'Test';
        $plan->id = 'test';
        $plan->amount = 10;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $this->assertFalse($plan->save());

        $this->assertCount(1, $errors);
        $this->assertEquals('An item already exists with ID: test', $errors->all()[0]);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => 'test',
            'object' => 'plan',
            'name' => 'Test',
            'currency' => 'usd',
            'amount' => 107.305,
            'description' => null,
            'notes' => null,
            'interval' => Interval::MONTH,
            'interval_count' => 1,
            'catalog_item' => null,
            'quantity_type' => 'constant',
            'pricing_mode' => 'per_unit',
            'tiers' => null,
            'metadata' => new stdClass(),
            'created_at' => self::$plan->created_at,
            'updated_at' => self::$plan->updated_at,
        ];

        $this->assertEquals($expected, self::$plan->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$plan->name = 'Testing';
        $this->assertTrue(self::$plan->save());
    }

    /**
     * @depends testCreate
     */
    public function testCannotChangeID(): void
    {
        self::$plan2->id = 'test-1234';
        self::$plan2->save();
        $this->assertNotEquals('test-1234', self::$plan2->id);
    }

    /**
     * @depends testCreate
     */
    public function testCannotChangePrice(): void
    {
        self::$plan2->amount = 100000;
        $this->assertTrue(self::$plan2->save());
        $this->assertNotEquals(100000, self::$plan2->amount);
    }

    /**
     * @depends testCreate
     */
    public function testCannotChangeInterval(): void
    {
        self::$plan2->interval = Interval::YEAR;
        self::$plan2->interval_count = 2;
        $this->assertTrue(self::$plan2->save());
        $this->assertNotEquals(2, self::$plan2->interval);
        $this->assertNotEquals(Interval::YEAR, self::$plan2->interval_count);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $plans = Plan::all();

        $this->assertCount(3, $plans);

        $find = [self::$plan->id, self::$plan2->id, self::$zeroPlan->id];
        foreach ($plans as $plan) {
            if (false !== ($key = array_search($plan->id, $find))) {
                unset($find[$key]);
            }
        }
        $this->assertCount(0, $find);
    }

    /**
     * @depends testCreate
     */
    public function testGetCurrent(): void
    {
        $this->assertEquals(self::$plan, Plan::getCurrent('test'));
        $this->assertNull(Plan::getCurrent('does-not-exist'));
    }

    /**
     * @depends testCreate
     */
    public function testNumSubscriptions(): void
    {
        $this->assertEquals(0, self::$plan->num_subscriptions);

        self::$subscription = self::getService('test.create_subscription')
            ->create([
                'customer' => self::$customer,
                'plan' => self::$plan,
                'start_date' => time() + 3600,
            ]);

        self::$subscription = self::getService('test.create_subscription')
            ->create([
                'customer' => self::$customer,
                'plan' => self::$plan2,
                'addons' => [['plan' => self::$plan]],
                'start_date' => time() + 3600,
            ]);

        $this->assertEquals(2, self::$plan->num_subscriptions);
    }

    /**
     * @depends testCreate
     */
    public function testArchivedNotHidden(): void
    {
        $this->assertTrue(self::$plan->archive());

        $plans = Plan::all();
        $this->assertCount(3, $plans);

        $plans = Plan::where('archived', true)->all();
        $this->assertCount(1, $plans);

        $plans = Plan::where('archived', false)->all();
        $this->assertCount(2, $plans);
    }

    /**
     * @depends testCreate
     */
    public function testMetadata(): void
    {
        $metadata = self::$plan->metadata;
        $metadata->test = true;
        self::$plan->metadata = $metadata;
        $this->assertTrue(self::$plan->save());
        $this->assertEquals((object) ['test' => true], self::$plan->metadata);

        self::$plan->metadata = (object) ['internal.id' => '12345'];
        $this->assertTrue(self::$plan->save());
        $this->assertEquals((object) ['internal.id' => '12345'], self::$plan->metadata);

        self::$plan->metadata = (object) ['array' => [], 'object' => new stdClass()];
        $this->assertTrue(self::$plan->save());
        $this->assertEquals((object) ['array' => [], 'object' => new stdClass()], self::$plan->metadata);
    }

    /**
     * @depends testCreate
     */
    public function testBadMetadata(): void
    {
        self::$plan->metadata = (object) [str_pad('', 41) => 'fail'];
        $this->assertFalse(self::$plan->save());

        self::$plan->metadata = (object) ['fail' => str_pad('', 256)];
        $this->assertFalse(self::$plan->save());

        self::$plan->metadata = (object) array_fill(0, 11, 'fail');
        $this->assertFalse(self::$plan->save());

        self::$plan->metadata = (object) [];
    }

    public function testInvalidTiers(): void
    {
        self::$planInvalidTiers = new Plan();
        $this->assertFalse(self::$planInvalidTiers->create([
            'name' => 'Test',
            'id' => 'test',
            'amount' => 0,
            'interval' => Interval::MONTH,
            'interval_count' => 1,
            'tiers' => [
                [
                    'min_qty' => null,
                    'max_qty' => 100,
                    'unit_cost' => 20,
                ],
                [
                    'min_qty' => 100,
                    'max_qty' => 200,
                    'unit_cost' => 40,
                ],
            ],
        ]));
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        // deleting is the same as archiving
        $this->assertTrue(self::$plan->delete());
        $this->assertTrue(self::$plan->persisted());
        $this->assertTrue(self::$plan->archived);
    }

    /**
     * @depends testDelete
     */
    public function testCanCreateIdAfterDelete(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->id = 'test';
        $plan->amount = 10;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $this->assertTrue($plan->save());
    }

    public function testArchiving(): void
    {
        $plan = new Plan();
        $plan->id = 'archived';
        $plan->name = 'Test';
        $plan->amount = 100;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $this->assertTrue($plan->save());

        // archive it
        $this->assertTrue($plan->archive());
        // todo
        // try to look it up
        $plan2 = Plan::find($plan->internal_id);
        $this->assertNull(Plan::getCurrent('archived'));

        // unarchive it
        $plan->archived = false;
        $this->assertTrue($plan->save());

        // archive it again
        $this->assertTrue($plan->save());
        $this->assertTrue($plan->save());
    }

    public function testCreateMissingID(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->amount = 0;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;

        $this->assertTrue($plan->save());
        $this->assertEquals(PricingObject::ID_LENGTH, strlen($plan->id));
    }

    /**
     * Assert that non-custom plans should have a non-null amount value.
     */
    public function testNonCustomAmount(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->pricing_mode = Plan::PRICING_PER_UNIT;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;

        $this->expectExceptionMessage('Non-custom plans are required to have an amount');
        $plan->saveorFail();
    }

    /**
     * Assert that custom plans should have a null amount value.
     */
    public function testCustomAmount(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->pricing_mode = Plan::PRICING_CUSTOM;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        // custom plans should always have a null amount
        $plan->amount = 1;
        $this->expectExceptionMessage('Amounts are not allowed on plans that have a custom pricing mode');
        $plan->saveorFail();
    }
}
