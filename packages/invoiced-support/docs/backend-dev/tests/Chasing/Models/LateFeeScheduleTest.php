<?php

namespace App\Tests\Chasing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Chasing\Models\LateFeeSchedule;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

class LateFeeScheduleTest extends AppTestCase
{
    private static LateFeeSchedule $lateFeeSchedule2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$lateFeeSchedule = new LateFeeSchedule();
        self::$lateFeeSchedule->name = 'test';
        self::$lateFeeSchedule->start_date = new CarbonImmutable('2021-07-01');
        self::$lateFeeSchedule->default = true;
        self::$lateFeeSchedule->amount = 5;
        self::$lateFeeSchedule->is_percent = true;
        self::$lateFeeSchedule->grace_period = 30;
        $this->assertTrue(self::$lateFeeSchedule->save());
        $this->assertEquals(self::$company->id(), self::$lateFeeSchedule->tenant_id);

        self::$lateFeeSchedule2 = new LateFeeSchedule();
        self::$lateFeeSchedule2->name = 'test2';
        self::$lateFeeSchedule2->start_date = CarbonImmutable::now();
        self::$lateFeeSchedule2->amount = 10;
        self::$lateFeeSchedule2->grace_period = 0;
        $this->assertTrue(self::$lateFeeSchedule2->save());
    }

    /**
     * @depends testCreate
     */
    public function testCannotCreateMultipleDefaults(): void
    {
        $lateFeeSchedule = new LateFeeSchedule();
        $lateFeeSchedule->name = 'test2';
        $lateFeeSchedule->default = true;
        $lateFeeSchedule->start_date = CarbonImmutable::now();
        $this->assertFalse($lateFeeSchedule->save());
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        $lateFeeSchedules = LateFeeSchedule::all();
        $this->assertCount(2, $lateFeeSchedules);
        $this->assertEquals(self::$lateFeeSchedule->id(), $lateFeeSchedules[0]->id());
        $this->assertEquals(self::$lateFeeSchedule2->id(), $lateFeeSchedules[1]->id());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$lateFeeSchedule->name = 'Renamed';
        $this->assertTrue(self::$lateFeeSchedule->save());

        // Cannot have 2 defaults
        self::$lateFeeSchedule2->default = true;
        $this->assertFalse(self::$lateFeeSchedule2->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$lateFeeSchedule->id,
            'amount' => 5.0,
            'default' => true,
            'enabled' => true,
            'grace_period' => 30,
            'is_percent' => true,
            'last_run' => null,
            'name' => 'Renamed',
            'recurring_days' => 0,
            'start_date' => (new DateTimeImmutable('2021-07-01'))->setTime(0, 0),
            'created_at' => self::$lateFeeSchedule->created_at,
            'updated_at' => self::$lateFeeSchedule->updated_at,
        ];

        $this->assertEquals($expected, self::$lateFeeSchedule->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testCustomerAssignment(): void
    {
        $customer = new Customer();
        $customer->name = 'Test Default';
        $customer->country = 'US';
        $customer->saveOrFail();

        $this->assertEquals(self::$lateFeeSchedule->id(), $customer->late_fee_schedule_id);

        $customer = new Customer();
        $customer->name = 'Test Default';
        $customer->country = 'US';
        $customer->late_fee_schedule = self::$lateFeeSchedule2;
        $customer->saveOrFail();

        $this->assertEquals(self::$lateFeeSchedule2->id(), $customer->late_fee_schedule_id);

        // cannot delete if assigned
        $this->assertFalse(self::$lateFeeSchedule->delete());
    }

    /**
     * @depends testCustomerAssignment
     */
    public function testCannotDeleteAssigned(): void
    {
        $this->assertFalse(self::$lateFeeSchedule->delete());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        Customer::query()->delete();
        $this->assertTrue(self::$lateFeeSchedule->delete());
    }
}
