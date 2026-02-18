<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Coupon;

class CouponTest extends RateTestBase
{
    protected static string $model = Coupon::class;
    protected static string $objectName = 'coupon';

    protected function getExpectedArrayRepresentation(): array
    {
        return [
            'id' => 'test-rate',
            'object' => static::$objectName,
            'name' => 'Test',
            'is_percent' => true,
            'currency' => null,
            'value' => 10,
            'duration' => 0,
            'exclusive' => false,
            'expiration_date' => null,
            'max_redemptions' => 0,
            'metadata' => new \stdClass(),
            'created_at' => self::$rate->created_at,
            'updated_at' => self::$rate->updated_at,
        ];
    }
}
