<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Shipping;

class ShippingTest extends AppliedRateTestBase
{
    protected static string $model = Shipping::class;
    protected static string $type = 'shipping';
}
