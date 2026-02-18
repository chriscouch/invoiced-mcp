<?php

namespace App\Tests\CashApplication\Models;

use App\CashApplication\Models\CreditBalance;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class CreditBalanceTest extends AppTestCase
{
    private static CreditBalance $balance;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasTransaction();
    }

    public function testCreate(): void
    {
        self::$balance = new CreditBalance();
        self::$balance->transaction_id = (int) self::$transaction->id();
        self::$balance->customer_id = (int) self::$customer->id();
        self::$balance->currency = 'usd';
        self::$balance->timestamp = (int) mktime(0, 0, 0, 8, 31, 2015);
        self::$balance->balance = 100;

        $this->assertTrue(self::$balance->save());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$balance->balance = 50;
        $this->assertTrue(self::$balance->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'timestamp' => mktime(0, 0, 0, 8, 31, 2015),
            'balance' => 50,
            'currency' => 'usd',
        ];

        $this->assertEquals($expected, self::$balance->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testLookup(): void
    {
        $this->assertEquals(0, CreditBalance::lookup(self::$customer, null, new CarbonImmutable('2015-08-30T23:59:59'))->toDecimal());

        $this->assertEquals(50, CreditBalance::lookup(self::$customer, null, new CarbonImmutable('2015-08-31T00:00:00'))->toDecimal());

        $this->assertEquals(50, CreditBalance::lookup(self::$customer)->toDecimal());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$balance->delete());
    }
}
