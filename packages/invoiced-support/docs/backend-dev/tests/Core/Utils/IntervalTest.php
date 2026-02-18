<?php

namespace App\Tests\Core\Utils;

use App\Core\Utils\ValueObjects\Interval;
use App\Tests\AppTestCase;
use InvalidArgumentException;

class IntervalTest extends AppTestCase
{
    public function testInvalidInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $interval = new Interval(1, 'eon');
    }

    public function testInvalidCountString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $interval = new Interval(0, 'month');
    }

    public function testGetters(): void
    {
        $interval = new Interval(1, 'week');
        $this->assertEquals('week', $interval->interval);
        $this->assertEquals(1, $interval->count);
    }

    public function testValidIntervals(): void
    {
        $interval = new Interval(2, 'day');
        $this->assertEquals('day', $interval->interval);
        $interval = new Interval(2, 'days');
        $this->assertEquals('day', $interval->interval);

        $interval = new Interval(2, 'week');
        $this->assertEquals('week', $interval->interval);
        $interval = new Interval(2, 'weeks');
        $this->assertEquals('week', $interval->interval);

        $interval = new Interval(2, 'month');
        $this->assertEquals('month', $interval->interval);
        $interval = new Interval(2, 'months');
        $this->assertEquals('month', $interval->interval);

        $interval = new Interval(2, 'year');
        $this->assertEquals('year', $interval->interval);
        $interval = new Interval(2, 'years');
        $this->assertEquals('year', $interval->interval);
    }

    public function testDuration(): void
    {
        $interval = new Interval(1, 'day');
        $this->assertEquals('1 day', $interval->duration());

        $interval = new Interval(2, 'day');
        $this->assertEquals('2 days', $interval->duration());

        $interval = new Interval(1, 'week');
        $this->assertEquals('1 week', $interval->duration());

        $interval = new Interval(2, 'week');
        $this->assertEquals('2 weeks', $interval->duration());

        $interval = new Interval(1, 'month');
        $this->assertEquals('1 month', $interval->duration());

        $interval = new Interval(2, 'month');
        $this->assertEquals('2 months', $interval->duration());

        $interval = new Interval(1, 'year');
        $this->assertEquals('1 year', $interval->duration());

        $interval = new Interval(2, 'year');
        $this->assertEquals('2 years', $interval->duration());
    }

    public function testToString(): void
    {
        $interval = new Interval(1, 'day');
        $this->assertEquals('daily', (string) $interval);

        $interval = new Interval(2, 'day');
        $this->assertEquals('every 2 days', (string) $interval);

        $interval = new Interval(1, 'week');
        $this->assertEquals('weekly', (string) $interval);

        $interval = new Interval(2, 'week');
        $this->assertEquals('every 2 weeks', (string) $interval);

        $interval = new Interval(1, 'month');
        $this->assertEquals('monthly', (string) $interval);

        $interval = new Interval(2, 'month');
        $this->assertEquals('every 2 months', (string) $interval);

        $interval = new Interval(1, 'year');
        $this->assertEquals('yearly', (string) $interval);

        $interval = new Interval(2, 'year');
        $this->assertEquals('every 2 years', (string) $interval);
    }

    public function testNumDays(): void
    {
        $interval = new Interval(1, 'day');
        $this->assertEquals(1, $interval->numDays());

        $interval = new Interval(2, 'day');
        $this->assertEquals(2, $interval->numDays());

        $interval = new Interval(1, 'week');
        $this->assertEquals(7, $interval->numDays());

        $interval = new Interval(2, 'week');
        $this->assertEquals(14, $interval->numDays());

        $interval = new Interval(1, 'month');
        $this->assertEquals(31, $interval->numDays());

        $interval = new Interval(2, 'month');
        $this->assertEquals(62, $interval->numDays());

        $interval = new Interval(1, 'year');
        $this->assertEquals(365, $interval->numDays());

        $interval = new Interval(2, 'year');
        $this->assertEquals(730, $interval->numDays());
    }

    public function testAddTo(): void
    {
        $interval = new Interval(1, 'day');
        $t = 100;
        $this->assertEquals(86500, $interval->addTo($t));

        $interval = new Interval(2, 'day');
        $t = 100;
        $this->assertEquals(172900, $interval->addTo($t));

        $interval = new Interval(1, 'week');
        $t = 100;
        $this->assertEquals(604900, $interval->addTo($t));

        $interval = new Interval(3, 'week');
        $t = 100;
        $this->assertEquals(1814500, $interval->addTo($t));

        $interval = new Interval(1, 'month');
        $t = 100;
        $this->assertEquals(2678500, $interval->addTo($t));

        $interval = new Interval(4, 'month');
        $t = 100;
        $this->assertEquals(10368100, $interval->addTo($t));

        $interval = new Interval(1, 'year');
        $t = 100;
        $this->assertEquals(31536100, $interval->addTo($t));

        $interval = new Interval(5, 'year');
        $t = 100;
        $this->assertEquals(157766500, $interval->addTo($t));
    }

    public function testEquals(): void
    {
        $interval = new Interval(1, 'day');

        $this->assertTrue($interval->equals($interval));

        $interval2 = new Interval(1, 'day');
        $this->assertTrue($interval->equals($interval2));

        $interval2 = new Interval(2, 'day');
        $this->assertFalse($interval->equals($interval2));

        $interval2 = new Interval(1, 'month');
        $this->assertFalse($interval->equals($interval2));
    }
}
