<?php

namespace App\Tests\AccountsPayable\Models;

use App\Tests\AppTestCase;

class AccountsPayableSettingsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testToArray(): void
    {
        $expected = [
            'aging_buckets' => [0, 8, 15, 31, 61],
            'aging_date' => 'date',
            'inbox_id' => self::$company->accounts_payable_settings->inbox_id,
        ];

        $this->assertEquals($expected, self::$company->accounts_payable_settings->toArray());
    }

    public function testEdit(): void
    {
        self::$company->accounts_payable_settings->aging_date = 'due_date';
        $this->assertTrue(self::$company->accounts_payable_settings->save());
    }

    public function testDelete(): void
    {
        $this->assertFalse(self::$company->accounts_payable_settings->delete());
    }
}
