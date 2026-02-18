<?php

namespace App\Tests\Chasing\Legacy;

use App\Chasing\Legacy\ChaseSchedule;
use App\Chasing\Legacy\ChaseScheduleStep;
use App\Tests\AppTestCase;

class ChaseScheduleTest extends AppTestCase
{
    public function testCompare(): void
    {
        $issuedStep = new ChaseScheduleStep('issued');
        $step2 = new ChaseScheduleStep('2');
        $step3 = new ChaseScheduleStep('3');
        $repeatingStep = new ChaseScheduleStep('~2');

        $this->assertEquals(-1, ChaseSchedule::compare($issuedStep, $repeatingStep));
        $this->assertEquals(0, ChaseSchedule::compare($issuedStep, $issuedStep));
        $this->assertEquals(-1, ChaseSchedule::compare($issuedStep, $step2));
        $this->assertEquals(1, ChaseSchedule::compare($step2, $issuedStep));

        $this->assertEquals(0, ChaseSchedule::compare($step2, $step2));
        $this->assertEquals(1, ChaseSchedule::compare($step3, $step2));
        $this->assertEquals(-1, ChaseSchedule::compare($step2, $step3));

        $this->assertEquals(-1, ChaseSchedule::compare($step2, $repeatingStep));
        $this->assertEquals(1, ChaseSchedule::compare($repeatingStep, $step2));
    }

    public function testBuildFromArray(): void
    {
        $sched = [
            (object) ['step' => '~3', 'action' => 'email'],
            (object) ['step' => -2, 'action' => 'email'],
            (object) ['step' => 0, 'action' => 'email'],
            (object) ['step' => 20, 'action' => 'email'],
            (object) ['step' => 4, 'action' => 'email'],
            (object) ['step' => '4', 'action' => 'email'],
            (object) ['step' => 7, 'action' => 'email'],
        ];
        $schedule = ChaseSchedule::buildFromArray($sched);
        $this->assertInstanceOf(ChaseSchedule::class, $schedule);
        $this->assertEquals([
           ['step' => -2, 'action' => 'email'],
           ['step' => 0, 'action' => 'email'],
           ['step' => 4, 'action' => 'email'],
           ['step' => 7, 'action' => 'email'],
           ['step' => 20, 'action' => 'email'],
           ['step' => '~3', 'action' => 'email'],
        ], $schedule->toArray(true));
        $steps = $schedule->getSteps();
        foreach ($steps as $step) {
            $this->assertInstanceOf(ChaseScheduleStep::class, $step);
        }
        $this->assertEquals(-2, $steps[0]->getStep());
        $this->assertEquals(0, $steps[1]->getStep());
        $this->assertEquals(4, $steps[2]->getStep());
        $this->assertEquals(7, $steps[3]->getStep());
        $this->assertEquals(20, $steps[4]->getStep());
        $this->assertEquals('~3', $steps[5]->getStep());

        $sched = [
            (object) ['step' => '~3', 'action' => 'email'],
            (object) ['step' => 'issued', 'action' => 'email'],
            (object) ['step' => -2, 'action' => 'email'],
            (object) ['step' => 0, 'action' => 'email'],
            (object) ['step' => 20, 'action' => 'email'],
            (object) ['step' => 4, 'action' => 'email'],
            (object) ['step' => '4', 'action' => 'email'],
            (object) ['step' => 7, 'action' => 'email'],
        ];
        $schedule = ChaseSchedule::buildFromArray($sched);
        $this->assertInstanceOf(ChaseSchedule::class, $schedule);
        $this->assertEquals([
           ['step' => 'issued', 'action' => 'email'],
           ['step' => -2, 'action' => 'email'],
           ['step' => 0, 'action' => 'email'],
           ['step' => 4, 'action' => 'email'],
           ['step' => 7, 'action' => 'email'],
           ['step' => 20, 'action' => 'email'],
           ['step' => '~3', 'action' => 'email'],
        ], $schedule->toArray(true));

        $sched = ['~7'];
        $schedule = ChaseSchedule::buildFromArray($sched);
        $this->assertInstanceOf(ChaseSchedule::class, $schedule);
        $this->assertEquals([['step' => '~7', 'action' => 'email']], $schedule->toArray(true));
    }

    public function testBuildFromArrayLegacy(): void
    {
        $sched = ['~3', -2, 0, 20, 4, '4', 7];
        $schedule = ChaseSchedule::buildFromArray($sched);
        $this->assertInstanceOf(ChaseSchedule::class, $schedule);
        $this->assertEquals([
            -2,
            0,
            4,
            7,
            20,
            '~3',
        ], $schedule->toArray());
        $steps = $schedule->getSteps();
        foreach ($steps as $step) {
            $this->assertInstanceOf(ChaseScheduleStep::class, $step);
        }
        $this->assertEquals(-2, $steps[0]->getStep());
        $this->assertEquals(0, $steps[1]->getStep());
        $this->assertEquals(4, $steps[2]->getStep());
        $this->assertEquals(7, $steps[3]->getStep());
        $this->assertEquals(20, $steps[4]->getStep());
        $this->assertEquals('~3', $steps[5]->getStep());

        $sched = ['~3', 'issued', -2, 0, 20, 4, '4', 7];
        $schedule = ChaseSchedule::buildFromArray($sched);
        $this->assertInstanceOf(ChaseSchedule::class, $schedule);
        $this->assertEquals([
            'issued',
            -2,
            0,
            4,
            7,
            20,
            '~3',
        ], $schedule->toArray());

        $sched = ['~7'];
        $schedule = ChaseSchedule::buildFromArray($sched);
        $this->assertInstanceOf(ChaseSchedule::class, $schedule);
        $this->assertEquals(['~7'], $schedule->toArray());
    }

    public function testIsValid(): void
    {
        $schedule = new ChaseSchedule([]);
        $this->assertTrue($schedule->isValid());

        $step1 = new ChaseScheduleStep('2');
        $schedule = new ChaseSchedule([$step1]);
        $this->assertTrue($schedule->isValid());

        $step1 = new ChaseScheduleStep('~7');
        $schedule = new ChaseSchedule([$step1]);
        $this->assertTrue($schedule->isValid());

        $step1 = new ChaseScheduleStep('issued');
        $schedule = new ChaseSchedule([$step1]);
        $this->assertTrue($schedule->isValid());

        $step1 = new ChaseScheduleStep('issued');
        $step2 = new ChaseScheduleStep('-2');
        $step3 = new ChaseScheduleStep('5');
        $step4 = new ChaseScheduleStep('~3');
        $schedule = new ChaseSchedule([$step1, $step2, $step3, $step4]);
        $this->assertTrue($schedule->isValid());

        $step1 = new ChaseScheduleStep('not_valid');
        $schedule = new ChaseSchedule([$step1]);
        $this->assertFalse($schedule->isValid());

        $step1 = new ChaseScheduleStep('~not_valid');
        $schedule = new ChaseSchedule([$step1]);
        $this->assertFalse($schedule->isValid());

        $step1 = new ChaseScheduleStep('~3');
        $step2 = new ChaseScheduleStep('~4');
        $schedule = new ChaseSchedule([$step1, $step2]);
        $this->assertFalse($schedule->isValid());
    }

    public function testNext(): void
    {
        $schedule = new ChaseSchedule([]);
        $this->assertNull($schedule->next(90, 100, 0));

        $this->assertNull($schedule->next(90, null, 0));

        $step1 = new ChaseScheduleStep('issued');
        $schedule = new ChaseSchedule([$step1]);
        $date = 1000000;
        $dueDate = null;

        // should = due date
        $lastSent = 0;
        $this->assertEquals([$date, 'email'], $schedule->next($date, $dueDate, $lastSent));

        // no more sends
        $lastSent = 1;
        $this->assertNull($schedule->next($date, $dueDate, $lastSent));

        $step1 = new ChaseScheduleStep('-5');
        $step2 = new ChaseScheduleStep('0');
        $step3 = new ChaseScheduleStep('10');
        $step4 = new ChaseScheduleStep('~3');
        $schedule = new ChaseSchedule([$step1, $step2, $step3, $step4]);
        $dueDate = 1000000;

        // should = due date - 5 days
        $lastSent = 0;
        $this->assertEquals([$dueDate - 5 * 86400, 'email'], $schedule->next($date, $dueDate, $lastSent));

        // should = due date - 5 days
        $lastSent = 100;
        $this->assertEquals([$dueDate - 5 * 86400, 'email'], $schedule->next($date, $dueDate, $lastSent));

        // should = due date
        $lastSent = $dueDate - 5 * 86400;
        $this->assertEquals([$dueDate, 'email'], $schedule->next($date, $dueDate, $lastSent));

        // should = due date + 10 days
        $lastSent = $dueDate + 1;
        $this->assertEquals([$dueDate + 10 * 86400, 'email'], $schedule->next($date, $dueDate, $lastSent));

        // should = due date + 13 days
        $lastSent = $dueDate + 10 * 86400 + 1;
        $this->assertEquals([$dueDate + 13 * 86400, 'email'], $schedule->next($date, $dueDate, $lastSent));

        // should satisfy:
        // i) multiple of 3 days away from due date + 10 days
        // ii) greater than lastSent
        $lastSent = 7000000;
        // multiples = ceil(lastSent - (due date + 10 days) / 3 days) = 20
        $this->assertEquals([($dueDate + 10 * 86400) + 20 * 3 * 86400, 'email'], $schedule->next($date, $dueDate, $lastSent));

        // test with schedule that ends
        $step1 = new ChaseScheduleStep('-5');
        $step2 = new ChaseScheduleStep('0');
        $step3 = new ChaseScheduleStep('10');
        $schedule = new ChaseSchedule([$step1, $step2, $step3]);
        $lastSent = 7000000;
        $this->assertNull($schedule->next($date, $dueDate, $lastSent));

        // test with repeat only schedule where lastSent < due date.
        // the schedule should behave as if '0' is the first
        // component before the repeat.
        // should = due date + 3 days
        $step1 = new ChaseScheduleStep('~3');
        $schedule = new ChaseSchedule([$step1]);
        $lastSent = 100;
        $this->assertEquals([$dueDate + 3 * 86400, 'email'], $schedule->next($date, $dueDate, $lastSent));
    }
}
