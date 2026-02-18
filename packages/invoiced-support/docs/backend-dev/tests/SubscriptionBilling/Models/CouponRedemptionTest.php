<?php

namespace App\Tests\SubscriptionBilling\Models;

use App\SubscriptionBilling\Models\CouponRedemption;
use App\Tests\AppTestCase;

class CouponRedemptionTest extends AppTestCase
{
    private static CouponRedemption $redemption;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCoupon();
    }

    public function testCreate(): void
    {
        self::$redemption = new CouponRedemption();
        self::$redemption->parent_type = 'subscription';
        self::$redemption->parent_id = -1;
        self::$redemption->setCoupon(self::$coupon);
        $this->assertTrue(self::$redemption->save());
        $this->assertEquals(self::$coupon->internal_id, self::$redemption->coupon_id);
        $this->assertEquals(self::$company->id(), self::$redemption->tenant_id);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$redemption->num_uses = 100;
        $this->assertTrue(self::$redemption->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $this->assertEquals(self::$coupon->toArray(), self::$redemption->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$redemption->delete());
    }
}
