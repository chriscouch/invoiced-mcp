<?php

namespace App\Core\Utils\ValueObjects;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * @property int    $count
 * @property string $interval
 */
class Interval implements \Stringable
{
    const DAY = 'day';
    const WEEK = 'week';
    const MONTH = 'month';
    const YEAR = 'year';

    protected static array $intervals = [
        'day' => self::DAY,
        'days' => self::DAY,
        'week' => self::WEEK,
        'weeks' => self::WEEK,
        'month' => self::MONTH,
        'months' => self::MONTH,
        'year' => self::YEAR,
        'years' => self::YEAR,
    ];

    private static array $singular = [
        self::DAY => 'day',
        self::WEEK => 'week',
        self::MONTH => 'month',
        self::YEAR => 'year',
    ];

    private static array $adverb = [
        self::DAY => 'daily',
        self::WEEK => 'weekly',
        self::MONTH => 'monthly',
        self::YEAR => 'yearly',
    ];

    private static array $plural = [
        self::DAY => 'days',
        self::WEEK => 'weeks',
        self::MONTH => 'months',
        self::YEAR => 'years',
    ];

    private static array $numDays = [
        self::DAY => 1,
        self::WEEK => 7,
        self::MONTH => 31,
        self::YEAR => 365,
    ];

    private int $_count;
    private string $_interval;

    public function __construct(int $count, string $interval)
    {
        if (!$interval) {
            throw new InvalidArgumentException("Invalid interval: $interval");
        }

        $interval = strtolower($interval);
        if (isset(self::$intervals[$interval])) {
            $interval = self::$intervals[$interval];
        } else {
            throw new InvalidArgumentException("Unsupported interval: $interval. Supported intervals are `day`, `week`, `month`, and `year`.");
        }

        if ($count < 1) {
            throw new InvalidArgumentException("Invalid count: $count");
        }

        $this->_count = $count;
        $this->_interval = $interval;
    }

    public function __get(string $k): mixed
    {
        if ('count' == $k) {
            return $this->_count;
        } elseif ('interval' == $k) {
            return $this->_interval;
        }

        return $this->$k;
    }

    /**
     * Converts a frequency array to a strtotime() compatible string.
     * i.e. "1 month" or "2 weeks".
     */
    public function duration(): string
    {
        $singular = array_value(self::$singular, $this->_interval);
        $plural = array_value(self::$plural, $this->_interval);

        if (1 == $this->_count) {
            return $this->_count.' '.$singular;
        }

        return $this->_count.' '.$plural;
    }

    /**
     * Gets the maximum number of days in the interval.
     */
    public function numDays(): int
    {
        return $this->_count * self::$numDays[$this->_interval];
    }

    /**
     * Adds this interval to a timestamp.
     *
     * @param int $t timestamp
     *
     * @return int new timestamp
     */
    public function addTo(int $t): int
    {
        return match ($this->_interval) {
            self::YEAR => $this->addYearsNoOverflow($t),
            self::MONTH => $this->addMonthsNoOverflow($t),
            default => (int)strtotime('+' . $this->duration(), $t)
        };
    }

    /**
     * Adds years to a timestamp without allowing overflow.
     *
     * @param int $timestamp timestamp
     *
     * @return int new timestamp
     */
    private function addYearsNoOverflow(int $timestamp): int
    {
        $currentTimeStamp = Carbon::createFromTimestamp($timestamp);
        $isLastDayOfTheMonth = $currentTimeStamp->isLastOfMonth();

        $carbonDateTime = Carbon::createFromTimestamp($timestamp)
            ->addYearsWithoutOverflow($this->_count);

        if ($isLastDayOfTheMonth) {
            $carbonDateTime->endOfMonth();
        }

        return $carbonDateTime
            ->setTime($currentTimeStamp->hour, $currentTimeStamp->minute, $currentTimeStamp->second)
            ->getTimestamp();
    }

    /**
     * Adds months to a timestamp without allowing overflow.
     *
     * @param int $timestamp timestamp
     *
     * @return int new timestamp
     */
    private function addMonthsNoOverflow(int $timestamp): int
    {
        $currentTimeStamp = Carbon::createFromTimestamp($timestamp);
        $isLastDayOfTheMonth = $currentTimeStamp->isLastOfMonth();

        $carbonDateTime = Carbon::createFromTimestamp($timestamp)
            ->addMonthsWithoutOverflow($this->_count);

        if ($isLastDayOfTheMonth) {
            $carbonDateTime->endOfMonth();
        }

        return $carbonDateTime
            ->setTime($currentTimeStamp->hour, $currentTimeStamp->minute, $currentTimeStamp->second)
            ->getTimestamp();
    }

    /**
     * Subtracts years from a timestamp without allowing overflow.
     *
     * @param int $timestamp timestamp
     *
     * @return int new timestamp
     */
    private function subYearsNoOverflow(int $timestamp): int
    {
        $currentTimeStamp = Carbon::createFromTimestamp($timestamp);
        $isLastDayOfTheMonth = $currentTimeStamp->isLastOfMonth();

        $carbonDateTime = Carbon::createFromTimestamp($timestamp)
            ->subYearsWithoutOverflow($this->_count);

        if ($isLastDayOfTheMonth) {
            $carbonDateTime->endOfMonth();
        }

        return $carbonDateTime
            ->setTime($currentTimeStamp->hour, $currentTimeStamp->minute, $currentTimeStamp->second)
            ->getTimestamp();
    }

    /**
     * Subtracts months from a timestamp without allowing overflow.
     *
     * @param int $timestamp timestamp
     *
     * @return int new timestamp
     */
    private function subMonthsNoOverflow(int $timestamp): int
    {
        $currentTimeStamp = Carbon::createFromTimestamp($timestamp);
        $isLastDayOfTheMonth = $currentTimeStamp->isLastOfMonth();

        $carbonDateTime = Carbon::createFromTimestamp($timestamp)
            ->subMonthsWithoutOverflow($this->_count);

        if ($isLastDayOfTheMonth) {
            $carbonDateTime->endOfMonth();
        }

        return $carbonDateTime
            ->setTime($currentTimeStamp->hour, $currentTimeStamp->minute, $currentTimeStamp->second)
            ->getTimestamp();
    }

    /**
     * Subtracts this interval to a timestamp.
     *
     * @param int $t timestamp
     *
     * @return int new timestamp
     */
    public function subFrom(int $t): int
    {
        return match ($this->_interval) {
            self::YEAR => $this->subYearsNoOverflow($t),
            self::MONTH => $this->subMonthsNoOverflow($t),
            default => (int) strtotime('-' . $this->duration(), $t)
        };
    }

    /**
     * Compares an interval to this one.
     */
    public function equals(self $interval): bool
    {
        return $interval->interval == $this->_interval && $interval->count == $this->_count;
    }

    /**
     * Converts a frequency array to a human-readable string.
     * i.e. "monthly" or "every 2 weeks".
     */
    public function __toString(): string
    {
        $singular = array_value(self::$adverb, $this->_interval);
        $plural = array_value(self::$plural, $this->_interval);

        if (1 == $this->_count) {
            return $singular;
        }

        return 'every '.$this->_count.' '.$plural;
    }
}
