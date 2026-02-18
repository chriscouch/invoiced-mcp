<?php

namespace App\Tests\CashApplication\Models;

use App\Tests\AppTestCase;

class CashApplicationSettingsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testToArray(): void
    {
        $expected = [
            'short_pay_amount' => 10,
            'short_pay_units' => 'percent',
        ];

        $this->assertEquals($expected, self::$company->cash_application_settings->toArray());
    }

    public function testEdit(): void
    {
        self::$company->cash_application_settings->short_pay_amount = 5;
        $this->assertTrue(self::$company->cash_application_settings->save());
    }

    public function testDelete(): void
    {
        $this->assertFalse(self::$company->cash_application_settings->delete());
    }
}
