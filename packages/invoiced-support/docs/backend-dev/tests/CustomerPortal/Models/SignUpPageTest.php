<?php

namespace App\Tests\CustomerPortal\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ValueObjects\Interval;
use App\CustomerPortal\Models\SignUpPage;
use App\SubscriptionBilling\Models\Plan;
use App\Tests\AppTestCase;

class SignUpPageTest extends AppTestCase
{
    private static SignUpPage $page;
    private static SignUpPage $page2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasPlan();
        self::hasCustomField(ObjectType::Subscription->typeName());
        self::hasItem();
    }

    public function testPlans(): void
    {
        $page = new SignUpPage();
        $page->tenant_id = -1;
        $page->plans = [self::$plan->id, 'does-not-exist'];
        $plans = $page->plans();

        $this->assertCount(1, $plans);
        $this->assertEquals(self::$plan, $plans[0]);
    }

    public function testCustomerUrl(): void
    {
        $customer = new Customer();
        $customer->client_id = 'customer_id';

        $page = new SignUpPage();
        $page->tenant_id = (int) self::$company->id();
        $page->client_id = 'page_id';
        $this->assertEquals('http://'.self::$company->username.'.invoiced.localhost:1234/pages/page_id/customer_id', $page->customerUrl($customer));
    }

    public function testCreate(): void
    {
        self::$page = new SignUpPage();
        self::$page->name = 'Test';
        self::$page->plans = [self::$plan->id];
        self::$page->custom_fields = [self::$customField->id];
        self::$page->setup_fee = self::$item->id;
        $this->assertTrue(self::$page->save());

        $this->assertEquals(self::$company->id(), self::$page->tenant_id);
        $this->assertEquals(48, strlen(self::$page->client_id));

        self::$page2 = new SignUpPage();
        self::$page2->name = 'Test 2';
        $this->assertTrue(self::$page2->save());
        $this->assertNotEquals(self::$page->client_id, self::$page2->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$page->name = 'New Name';
        $this->assertTrue(self::$page->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $pages = SignUpPage::all();

        $this->assertCount(2, $pages);

        // look for our 3 known pages
        $find = [self::$page->id(), self::$page2->id()];
        foreach ($pages as $page) {
            if (false !== ($key = array_search($page->id(), $find))) {
                unset($find[$key]);
            }
        }
        $this->assertCount(0, $find);
    }

    /**
     * @depends testCreate
     */
    public function testFindClientId(): void
    {
        $this->assertNull(SignUpPage::findClientId(''));
        $this->assertNull(SignUpPage::findClientId('1234'));

        $this->assertEquals(self::$page->id(), SignUpPage::findClientId(self::$page->client_id)->id()); /* @phpstan-ignore-line */

        $old = self::$page->client_id;
        self::$page->refreshClientId();
        $this->assertNotEquals($old, self::$page->client_id);

        // set client ID in the past
        self::$page->refreshClientId(false, strtotime('-1 year'));
        /** @var SignUpPage $obj */
        $obj = SignUpPage::findClientId(self::$page->client_id);

        // set the client ID to expire soon
        self::$page->refreshClientId(false, strtotime('+29 days'));
        /** @var SignUpPage $obj */
        $obj = SignUpPage::findClientId(self::$page->client_id);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$page->id(),
            'name' => 'New Name',
            'type' => SignUpPage::TYPE_RECURRING,
            'plans' => [
                self::$plan->toArray(),
            ],
            'setup_fee' => self::$item->toArray(),
            'taxes' => [],
            'has_quantity' => false,
            'trial_period_days' => 0,
            'snap_to_nth_day' => null,
            'has_coupon_code' => false,
            'header_text' => null,
            'billing_address' => false,
            'shipping_address' => false,
            'custom_fields' => [
                self::$customField->toArray(),
            ],
            'tos_url' => null,
            'thanks_url' => null,
            'allow_multiple_subscriptions' => false,
            'url' => 'http://'.self::$company->username.'.invoiced.localhost:1234/pages/'.self::$page->client_id,
            'created_at' => self::$page->created_at,
            'updated_at' => self::$page->updated_at,
        ];

        $this->assertEquals($expected, self::$page->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$page->delete());
    }

    public function testCustomPlan(): void
    {
        $plan = new Plan();
        $plan->name = 'Test';
        $plan->pricing_mode = Plan::PRICING_CUSTOM;
        $plan->interval = Interval::MONTH;
        $plan->interval_count = 1;
        $plan->saveOrFail();

        $page = new SignUpPage();
        $page->tenant_id = (int) self::$company->id();
        $page->plans = [$plan->id];

        $this->expectExceptionMessage('Custom priced plans are not allowed on sign up pages');
        $page->saveOrFail();
    }
}
