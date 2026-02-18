<?php

namespace App\Tests\CustomerPortal\Models;

use App\Core\Utils\ValueObjects\Interval;
use App\CustomerPortal\Models\SignUpPage;
use App\CustomerPortal\Models\SignUpPageAddon;
use App\SubscriptionBilling\Models\Plan;
use App\Tests\AppTestCase;

class SignUpPageAddonTest extends AppTestCase
{
    private static SignUpPage $page;
    private static SignUpPageAddon $addon;
    private static SignUpPageAddon $addon2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasPlan();
        self::hasItem();

        self::$page = new SignUpPage();
        self::$page->name = 'Test';
        self::$page->plans = [self::$plan->id];
        self::$page->saveOrFail();
    }

    public function testCreate(): void
    {
        self::$addon = new SignUpPageAddon();
        self::$addon->catalog_item = self::$item->id;
        self::$addon->sign_up_page = (int) self::$page->id();
        self::$addon->type = SignUpPageAddon::TYPE_QUANTITY;
        self::$addon->recurring = true;
        $this->assertTrue(self::$addon->save());

        self::$addon2 = new SignUpPageAddon();
        self::$addon2->setPlan(self::$plan);
        self::$addon2->sign_up_page = (int) self::$page->id();
        self::$addon2->type = SignUpPageAddon::TYPE_QUANTITY;
        self::$addon2->recurring = true;
        $this->assertTrue(self::$addon2->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$addon->recurring = false;
        $this->assertTrue(self::$addon->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $addons = SignUpPageAddon::all();

        $this->assertCount(2, $addons);

        $find = [self::$addon->id(), self::$addon2->id()];
        foreach ($addons as $addon) {
            if (false !== ($key = array_search($addon->id(), $find))) {
                unset($find[$key]);
            }
        }
        $this->assertCount(0, $find);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$addon->id(),
            'sign_up_page' => self::$page->id(),
            'catalog_item' => self::$item->toArray(),
            'plan' => null,
            'recurring' => false,
            'type' => SignUpPageAddon::TYPE_QUANTITY,
            'required' => false,
            'order' => null,
            'created_at' => self::$addon->created_at,
            'updated_at' => self::$addon->updated_at,
        ];

        $this->assertEquals($expected, self::$addon->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testToArrayPlan(): void
    {
        $expected = [
            'id' => self::$addon2->id(),
            'sign_up_page' => self::$page->id(),
            'catalog_item' => null,
            'plan' => self::$plan->toArray(),
            'recurring' => true,
            'type' => SignUpPageAddon::TYPE_QUANTITY,
            'required' => false,
            'order' => null,
            'created_at' => self::$addon2->created_at,
            'updated_at' => self::$addon2->updated_at,
        ];

        $this->assertEquals($expected, self::$addon2->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$addon->delete());
    }

    public function testCustomPlan(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->pricing_mode = Plan::PRICING_CUSTOM;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $addon = new SignUpPageAddon();
        $addon->tenant_id = (int) self::$company->id();
        $addon->setPlan($plan);

        $this->expectExceptionMessage('Custom priced plans are not allowed on sign up pages');
        $addon->saveOrFail();
    }
}
