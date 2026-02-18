<?php

namespace App\Tests\SubscriptionBilling\Libs;

use App\Core\Utils\ValueObjects\Interval;
use App\SubscriptionBilling\Libs\DateSnapper;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class DateSnapperTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        date_default_timezone_set('UTC');
        self::hasCompany();
    }

    private function getSnapper(?Interval $interval = null): DateSnapper
    {
        if (!$interval) {
            $interval = new Interval(1, Interval::MONTH);
        }

        $snapper = new DateSnapper($interval);

        return $snapper;
    }

    public function testGetInterval(): void
    {
        $snapper = $this->getSnapper();
        $interval = $snapper->getInterval();
        $this->assertInstanceOf(Interval::class, $interval);
        $this->assertEquals(1, $interval->count);
        $this->assertEquals('month', $interval->interval);
    }

    public function testValidateTooSmall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $snapper = $this->getSnapper();
        $snapper->validate(0);
    }

    public function testValidateTooLarge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $snapper = $this->getSnapper();
        $snapper->validate(32);
    }

    public function testValidate(): void
    {
        $snapper = $this->getSnapper();
        $this->assertEquals($snapper, $snapper->validate(1));
    }

    public function testIsNthDayWeek(): void
    {
        $snapper = $this->getSnapper(new Interval(1, Interval::WEEK));
        $t = new CarbonImmutable('2018-05-27');

        $this->assertFalse($snapper->isNthDay(1, $t)); // Monday
        $this->assertFalse($snapper->isNthDay(2, $t));
        $this->assertFalse($snapper->isNthDay(3, $t));
        $this->assertFalse($snapper->isNthDay(4, $t));
        $this->assertFalse($snapper->isNthDay(5, $t));
        $this->assertFalse($snapper->isNthDay(6, $t));
        $this->assertTrue($snapper->isNthDay(7, $t)); // Sunday
    }

    public function testIsNthDayMonth(): void
    {
        $snapper = $this->getSnapper();
        $t = new CarbonImmutable('2018-01-01');

        for ($i = 1; $i <= 31; ++$i) {
            if (1 == $i) {
                $this->assertTrue($snapper->isNthDay($i, $t), 'First of month should be Nth day: '.$i);
            } else {
                $this->assertFalse($snapper->isNthDay($i, $t), 'First of month should not be Nth day: '.$i);
            }
        }
    }

    public function testIsNthDayMonthEdgeCase(): void
    {
        $snapper = $this->getSnapper();
        $t = new CarbonImmutable('2017-02-28');

        $this->assertTrue($snapper->isNthDay(31, $t));
        $this->assertTrue($snapper->isNthDay(30, $t));
        $this->assertTrue($snapper->isNthDay(29, $t));
    }

    public function testIsNthDayYear(): void
    {
        $snapper = $this->getSnapper(new Interval(1, Interval::YEAR));
        $t = new CarbonImmutable('2019-01-01');

        for ($i = 1; $i <= 365; ++$i) {
            if (1 == $i) {
                $this->assertTrue($snapper->isNthDay($i, $t), 'First of year should be Nth day: '.$i);
            } else {
                $this->assertFalse($snapper->isNthDay($i, $t), 'First of year should not be Nth day: '.$i);
            }
        }
    }

    public function testNextInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $snapper = $this->getSnapper();
        $t = new CarbonImmutable('2017-01-02T02:49:30Z');

        $snapper->next(32, $t);
    }

    public function testNextDayNotAllowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $snapper = $this->getSnapper(new Interval(1, Interval::DAY));
        $t = new CarbonImmutable('2017-01-02T02:49:30Z');

        $snapper->next(1, $t);
    }

    public function testNextWeekly(): void
    {
        $snapper = $this->getSnapper(new Interval(1, Interval::WEEK));

        // Set current time to Monday
        $t = new CarbonImmutable('2017-01-02Z02:49:30');

        // Snap to Tuesday
        $expected = new CarbonImmutable('2017-01-03');
        $this->assertEquals($expected, $snapper->next(2, $t));

        // Snap to Wednesday
        $expected = new CarbonImmutable('2017-01-04');
        $this->assertEquals($expected, $snapper->next(3, $t));

        // Snap to Thursday
        $expected = new CarbonImmutable('2017-01-05');
        $this->assertEquals($expected, $snapper->next(4, $t));

        // Snap to Friday
        $expected = new CarbonImmutable('2017-01-06');
        $this->assertEquals($expected, $snapper->next(5, $t));

        // Snap to Saturday
        $expected = new CarbonImmutable('2017-01-07');
        $this->assertEquals($expected, $snapper->next(6, $t));

        // Snap to Sunday
        $expected = new CarbonImmutable('2017-01-08');
        $this->assertEquals($expected, $snapper->next(7, $t));

        // Snap to Monday
        $t = new CarbonImmutable('2017-01-08T02:49:30Z');
        $expected = new CarbonImmutable('2017-01-09');
        $this->assertEquals($expected, $snapper->next(1, $t));
    }

    public function testNextMonthly(): void
    {
        $snapper = $this->getSnapper();

        // check a partial cycle
        $t = new CarbonImmutable('2017-01-02T02:49:30Z');

        $expected = new CarbonImmutable('2017-02-01');
        $this->assertEquals($expected, $snapper->next(1, $t));

        $expected = new CarbonImmutable('2017-01-03');
        $this->assertEquals($expected, $snapper->next(3, $t));

        // check a full cycle
        $t = new CarbonImmutable('2017-01-01T02:49:30Z');
        $expected = new CarbonImmutable('2017-02-01');
        $this->assertEquals($expected, $snapper->next(1, $t));

        // edge cases:
        // snapping Jan 29 should go to Feb 28
        $t = new CarbonImmutable('2017-01-29T02:49:30Z');
        $expected = new CarbonImmutable('2017-02-28');
        $this->assertEquals($expected, $snapper->next(29, $t));

        // snapping Mar 31 should go to Apr 30
        $t = new CarbonImmutable('2017-03-31T02:49:30Z');
        $expected = new CarbonImmutable('2017-04-30');
        $this->assertEquals($expected, $snapper->next(31, $t));
    }

    public function testNextQuarterly(): void
    {
        $snapper = $this->getSnapper(new Interval(3, 'month'));

        // check a partial cycle
        $t = new CarbonImmutable('2017-01-02T02:49:30Z');

        $expected = new CarbonImmutable('2017-03-01');
        $this->assertEquals($expected, $snapper->next(1, $t));

        $expected = new CarbonImmutable('2017-03-03');
        $this->assertEquals($expected, $snapper->next(3, $t));

        // check a full cycle
        $t = new CarbonImmutable('2017-01-01T02:49:30Z');
        $expected = new CarbonImmutable('2017-04-01');
        $this->assertEquals($expected, $snapper->next(1, $t));

        // edge cases:
        // snapping Nov 29 should go to Feb 28
        $t = new CarbonImmutable('2016-11-29T02:49:30Z');
        $expected = new CarbonImmutable('2017-02-28');
        $this->assertEquals($expected, $snapper->next(29, $t));

        // snapping Mar 31 should go to Jun 30
        $t = new CarbonImmutable('2017-03-31T02:49:30Z');
        $expected = new CarbonImmutable('2017-06-30');
        $this->assertEquals($expected, $snapper->next(31, $t));
    }

    public function testNextYearly(): void
    {
        $snapper = $this->getSnapper(new Interval(1, Interval::YEAR));
        $t = new CarbonImmutable('2017-01-02T02:49:30Z');

        // Check a partial cycle
        $expected = new CarbonImmutable('2018-01-01');
        $this->assertEquals($expected, $snapper->next(1, $t));

        // Check a full cycle
        $expected = new CarbonImmutable('2017-02-19'); // 50th day of year
        $this->assertEquals($expected, $snapper->next(50, $t));
    }

    public function testNextAlwaysInFuture(): void
    {
        // weekly
        $snapper = $this->getSnapper(new Interval(1, Interval::WEEK));
        $t = new CarbonImmutable('2017-01-02');
        $expected = new CarbonImmutable('2017-01-09'); // Monday
        $this->assertEquals($expected, $snapper->next(1, $t));

        // monthly
        $snapper = $this->getSnapper();
        $t = new CarbonImmutable('2017-01-01');
        $expected = new CarbonImmutable('2017-02-01');
        $this->assertEquals($expected, $snapper->next(1, $t));

        // qarterly
        $snapper = $this->getSnapper(new Interval(3, Interval::MONTH));
        $t = new CarbonImmutable('2017-01-01');
        $expected = new CarbonImmutable('2017-04-01');
        $this->assertEquals($expected, $snapper->next(1, $t));

        // yearly
        $snapper = $this->getSnapper(new Interval(1, Interval::YEAR));
        $t = new CarbonImmutable('2017-01-01');
        $expected = new CarbonImmutable('2018-01-01');
        $this->assertEquals($expected, $snapper->next(1, $t));
    }
}
