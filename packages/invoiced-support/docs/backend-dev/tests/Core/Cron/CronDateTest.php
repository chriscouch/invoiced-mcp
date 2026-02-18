<?php

namespace App\Tests\Core\Cron;

use App\Core\Cron\ValueObjects\CronDate;
use App\Tests\AppTestCase;

class CronDateTest extends AppTestCase
{
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        date_default_timezone_set('UTC'); // set global timezone back
    }

    public function testGetNextRunContinuous(): void
    {
        $lastRun = (int) mktime(0, 0, 5, 6, 16, 2016);
        $date = new CronDate('* * * * *', $lastRun);

        // next run should be the next minute
        $expected = (int) mktime(0, 1, 0, 6, 16, 2016);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testGetNextRunContinuousAtBoundary(): void
    {
        $lastRun = (int) mktime(0, 0, 0, 6, 16, 2016);
        $date = new CronDate('* * * * *', $lastRun);

        // next run should be the next minute
        $expected = (int) mktime(0, 1, 0, 6, 16, 2016);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testGetNextRunMinuteAtBoundary(): void
    {
        $lastRun = (int) mktime(0, 1, 0, 6, 16, 2016);
        $date = new CronDate('1 * * * *', $lastRun);

        // next run should be the next hour
        $expected = (int) mktime(1, 1, 0, 6, 16, 2016);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testGetNextRunNextMinute(): void
    {
        $lastRun = (int) mktime(0, 2, 0, 6, 16, 2016);
        $date = new CronDate('1 * * * *', $lastRun);

        // next run should be the second minute on the next hour
        $expected = (int) mktime(1, 1, 0, 6, 16, 2016);
        $this->assertEquals($expected, $date->getNextRun());
    }

    public function testGetNextRunHourAtBoundary(): void
    {
        $lastRun = (int) mktime(1, 0, 0, 6, 16, 2016);
        $date = new CronDate('0 1 * * *', $lastRun);

        // next run should be the next day
        $expected = (int) mktime(1, 0, 0, 6, 17, 2016);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testGetNextRunNextHour(): void
    {
        $lastRun = (int) mktime(2, 0, 0, 6, 16, 2016);
        // next run should be the first hour on the next day
        $expected = (int) mktime(1, 0, 0, 6, 17, 2016);

        $date = new CronDate('0 1 * * *', $lastRun);
        $this->assertEquals($expected, $date->getNextRun());
    }

    public function testGetNextRunDayOfWeekAtBoundary(): void
    {
        $lastRun = (int) mktime(0, 0, 0, 6, 20, 2016);
        $date = new CronDate('0 0 * * 1', $lastRun);

        // next run should be the next week
        $expected = (int) mktime(0, 0, 0, 6, 27, 2016);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testGetNextRunNextDayOfWeek(): void
    {
        $lastRun = (int) mktime(0, 0, 0, 6, 16, 2016);
        $date = new CronDate('0 0 * * 1', $lastRun);

        // next run should be the next Monday
        $expected = (int) mktime(0, 0, 0, 6, 20, 2016);
        $this->assertEquals($expected, $date->getNextRun());
    }

    public function testGetNextRunDayOfMonthAtBoundary(): void
    {
        $lastRun = (int) mktime(0, 0, 0, 7, 1, 2016);
        $date = new CronDate('0 0 1 * *', $lastRun);

        // next run should be the next first of the month
        $expected = (int) mktime(0, 0, 0, 8, 1, 2016);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testGetNextRunNextDayOfMonth(): void
    {
        $lastRun = (int) mktime(0, 0, 0, 6, 16, 2016);
        // next run should be the next first of the month
        $expected = (int) mktime(0, 0, 0, 7, 1, 2016);

        $date = new CronDate('0 0 1 * *', $lastRun);
        $this->assertEquals($expected, $date->getNextRun());
    }

    public function testGetNextRunMonthAtBoundary(): void
    {
        $lastRun = (int) mktime(0, 0, 0, 1, 1, 2017);
        $date = new CronDate('0 0 1 1 *', $lastRun);

        // next run should be the next January 1
        $expected = (int) mktime(0, 0, 0, 1, 1, 2018);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testGetNextRunAll(): void
    {
        $lastRun = (int) mktime(0, 0, 0, 6, 16, 2016);
        $date = new CronDate('0 0 1 1 1', $lastRun);
        // next run should be the next Monday
        // that is the first day of the
        // first month of the year, at midnight

        // we have had a bug in previous implementation here.
        // From Cron man:
        // Note: The day of a command's execution can be specified by two fields
        // — day of month, and day of week. If both fields are restricted (i.e., aren't *),
        // the command will be run when either field matches the current time.
        // For example, ``30 4 1,15 * 5'' would cause a command to be r
        // un at 4:30 am on the 1st and 15th of each month, plus every Friday.
        // One can, however, achieve the desired result by adding a test
        // to the command (see the last example in EXAMPLE CRON FILE below).
        $expected = (int) mktime(0, 0, 0, 1, 1, 2017);
        $this->assertEquals($expected, $date->getNextRun());
    }

    public function testGetNextRunChangedTimeZone(): void
    {
        $lastRun = (int) gmmktime(0, 0, 0, 1, 1, 2017);

        date_default_timezone_set('America/Chicago');
        $date = new CronDate('0 0 1 1 *', $lastRun);

        // next run should be the next January 1 UTC
        $expected = (int) gmmktime(0, 0, 0, 1, 1, 2018);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testEveryFiveMinute(): void
    {
        $lastRun = (int) gmmktime(0, 0, 0, 1, 1, 2017);
        $expected = (int) gmmktime(0, 5, 0, 1, 1, 2017);

        date_default_timezone_set('America/Chicago');
        $date = new CronDate('*/5 * * * *', $lastRun);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testEveryHour(): void
    {
        $lastRun = (int) gmmktime(0, 0, 0, 1, 1, 2017);
        $expected = (int) gmmktime(1, 0, 0, 1, 1, 2017);

        $date = new CronDate('0 * * * *', $lastRun);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));

        $date = new CronDate('@hourly', $lastRun);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }

    public function testEveryMonth(): void
    {
        $lastRun = (int) gmmktime(0, 0, 0, 1, 1, 2017);
        $expected = (int) gmmktime(0, 0, 0, 2, 1, 2017);

        $date = new CronDate('0 0 1 * * ', $lastRun);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));

        $date = new CronDate('@monthly', $lastRun);
        $this->assertEquals(date('c', $expected), date('c', $date->getNextRun()));
    }
}
