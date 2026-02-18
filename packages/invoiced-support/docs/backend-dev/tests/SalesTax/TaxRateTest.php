<?php

namespace App\Tests\SalesTax;

use App\SalesTax\Models\TaxRate;
use App\Tests\AccountsReceivable\Models\RateTestBase;

class TaxRateTest extends RateTestBase
{
    protected static string $model = TaxRate::class;
    protected static string $objectName = 'tax_rate';

    protected function getExpectedArrayRepresentation(): array
    {
        return [
            'id' => 'test-rate',
            'object' => static::$objectName,
            'name' => 'Test',
            'is_percent' => true,
            'currency' => null,
            'value' => 10,
            'inclusive' => false,
            'metadata' => new \stdClass(),
            'created_at' => self::$rate->created_at,
            'updated_at' => self::$rate->updated_at,
        ];
    }
}
