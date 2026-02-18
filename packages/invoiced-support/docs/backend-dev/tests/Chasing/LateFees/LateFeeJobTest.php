<?php

namespace App\Tests\Chasing\LateFees;

use App\Chasing\Models\LateFeeSchedule;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class LateFeeJobTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasLateFeeSchedule();

        $lateFeeSchedule = new LateFeeSchedule();
        $lateFeeSchedule->name = 'Enabled';
        $lateFeeSchedule->start_date = new CarbonImmutable('2021-07-08');
        $lateFeeSchedule->amount = 10;
        $lateFeeSchedule->is_percent = false;
        $lateFeeSchedule->grace_period = 5;
        $lateFeeSchedule->enabled = false;
        $lateFeeSchedule->saveOrFail();
    }

    public function testGetSchedules(): void
    {
        $job = self::getService('test.late_fee_job');
        $schedules = $job->getTasks();
        $this->assertCount(1, $schedules);
        $this->assertEquals(self::$lateFeeSchedule->id(), $schedules[0]->id());
    }
}
