<?php

namespace App\Tests\CashApplication\Models;

use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\CreditBalanceAdjustment;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\Tests\AppTestCase;

class CreditBalanceAdjustmentTest extends AppTestCase
{
    private static CreditBalanceAdjustment $adjustment;
    private static CreditBalanceAdjustment $adjustment2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
    }

    public function testCreate(): void
    {
        EventSpool::enable();

        self::$adjustment = new CreditBalanceAdjustment();
        self::$adjustment->setCustomer(self::$customer);
        self::$adjustment->currency = 'usd';
        self::$adjustment->date = (int) mktime(0, 0, 0, 8, 31, 2015);
        self::$adjustment->amount = 100;

        $this->assertTrue(self::$adjustment->save());

        $this->assertEquals(100.0, CreditBalance::lookup(self::$customer)->toDecimal());

        self::$adjustment2 = new CreditBalanceAdjustment();
        self::$adjustment2->setCustomer(self::$customer);
        self::$adjustment2->currency = 'usd';
        self::$adjustment2->date = (int) mktime(0, 0, 0, 8, 31, 2015);
        self::$adjustment2->amount = -25;

        $this->assertTrue(self::$adjustment2->save());

        $this->assertEquals(75.0, CreditBalance::lookup(self::$customer)->toDecimal());
    }

    /**
     * @depends testCreate
     */
    public function testEventCreated(): void
    {
        self::getService('test.event_spool')->flush(); // write out events

        $n = Event::where('type_id', EventType::TransactionCreated->toInteger())
            ->where('object_type_id', ObjectType::Transaction->value)
            ->where('object_id', self::$adjustment)
            ->count();
        $this->assertEquals(1, $n);

        $n = Event::where('type_id', EventType::TransactionCreated->toInteger())
            ->where('object_type_id', ObjectType::Transaction->value)
            ->where('object_id', self::$adjustment2)
            ->count();
        $this->assertEquals(1, $n);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$adjustment->amount = 50;
        $this->assertTrue(self::$adjustment->save());
        $this->assertEquals(25.0, CreditBalance::lookup(self::$customer)->toDecimal());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$adjustment->id(),
            'object' => 'credit_balance_adjustment',
            'customer' => self::$customer->id(),
            'date' => mktime(0, 0, 0, 8, 31, 2015),
            'currency' => 'usd',
            'amount' => 50.0,
            'notes' => null,
            'created_at' => self::$adjustment->created_at,
        ];

        $this->assertEquals($expected, self::$adjustment->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        // negative adjustment must be deleted first
        $this->assertFalse(self::$adjustment->delete());
        $this->assertTrue(self::$adjustment2->delete());
        $this->assertTrue(self::$adjustment->delete());

        $this->assertEquals(0.0, CreditBalance::lookup(self::$customer)->toDecimal());
    }
}
