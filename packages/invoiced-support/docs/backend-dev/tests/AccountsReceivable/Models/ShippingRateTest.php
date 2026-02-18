<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\ShippingRate;

class ShippingRateTest extends RateTestBase
{
    protected static string $model = ShippingRate::class;
    protected static string $objectName = 'shipping_rate';
}
