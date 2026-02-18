<?php

namespace App\Tests\AccountsReceivable\Models;

use App\AccountsReceivable\Models\Tax;

class TaxTest extends AppliedRateTestBase
{
    protected static string $model = Tax::class;
    protected static string $type = 'tax';
}
