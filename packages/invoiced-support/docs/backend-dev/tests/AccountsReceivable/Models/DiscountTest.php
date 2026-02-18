<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Discount;

class DiscountTest extends AppliedRateTestBase
{
    protected static string $model = Discount::class;
    protected static string $type = 'discount';
    protected static array $extraProperties = ['expires', 'from_payment_terms'];
}
