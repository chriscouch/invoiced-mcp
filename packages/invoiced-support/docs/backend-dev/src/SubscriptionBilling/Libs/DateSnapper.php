<?php

namespace App\SubscriptionBilling\Libs;

use App\Core\Utils\ValueObjects\Interval;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Handles date calculations when using calendar billing.
 */
final class DateSnapper
{
    private static array $supportedIntervals = [
        Interval::WEEK,
        Interval::MONTH,
        Interval::YEAR,
    ];

    private static array $dateMetrics = [
        Interval::WEEK => 'N', // day of week (1-7)
        Interval::MONTH => 'j', // day of month (1-31)
        Interval::YEAR => 'z', // day of year (0-365)
    ];

    private static array $boundaryMetrics = [
        Interval::WEEK => 'W', // week of year
        Interval::MONTH => 'n', // month of year
        Interval::YEAR => 'Y', // year
    ];

    public function __construct(private Interval $interval)
    {
    }

    /**
     * Gets the billing interval.
     */
    public function getInterval(): Interval
    {
        return $this->interval;
    }

    /**
     * Validates an Nth day input.
     *
     * @throws InvalidArgumentException if the input is invalid
     *
     * @return $this
     */
    public function validate(int $n): self
    {
        $interval = $this->interval->interval;
        if (!in_array($interval, self::$supportedIntervals)) {
            throw new InvalidArgumentException('Calendar snapping not supported for interval: '.$interval);
        }

        $max = $this->interval->numDays();
        if (!is_integer($n) || $n < 1 || $n > $max) {
            throw new InvalidArgumentException("Invalid snap to day for {$this->interval} intervals: $n");
        }

        return $this;
    }

    /**
     * Checks if it's the Nth day.
     *
     * @param int $n target Nth day
     */
    public function isNthDay(int $n, CarbonImmutable $t): bool
    {
        // determine the php date() format to use to calculate the Nth day
        $interval = $this->interval->interval;
        $metric = self::$dateMetrics[$interval];

        // shift N to account for first day of year being 0
        if (Interval::YEAR == $interval) {
            --$n;
        }

        if ($t->format($metric) == $n) {
            return true;
        }

        // Handles the edge case where the Nth day of the month
        // is greater than the # of days in certain months (> 28).
        // If there are less than N days in a month then we just
        // go with the last day of the month.
        if (Interval::MONTH == $interval) {
            $numDaysInMonth = (int) $t->format('t');
            if ($n > $numDaysInMonth) {
                return (int) $t->format('j') == $numDaysInMonth;
            }
        }

        return false;
    }

    /**
     * Calculates the next timestamp that is snapped to the
     * given Nth day. The resulting timestamp will be the start of the day.
     *
     * The target Nth day can be in these ranges for a given interval:
     * - Week: 1 (for Monday) through 7 (for Sunday)
     * - Month: 1 - 31
     * - Year: 1 - 365
     *
     * @param int $n target Nth day
     *
     * @throws InvalidArgumentException if the input is invalid
     */
    public function next(int $n, CarbonImmutable $start): CarbonImmutable
    {
        $this->validate($n);

        // Ensures that the generated timestamp crosses the appropriate
        // number of boundaries. The boundary is the interval used, either
        // week, month, or year. This is needed when the interval count is > 1.
        // Without this quarterly billing would only snap to the next month instead
        // of 3 months in the future.
        $isPartialCycle = !$this->isNthDay($n, $start);
        if ($isPartialCycle) {
            // If this is a partial cycle (i.e. the customer signed up mid-cycle)
            // then we want a boundary constraint of N - 1 because
            // the billing cycle should ALWAYS be less
            // than the full interval (1 month, 1 quarter, 1 year, etc).
            $minBoundaries = $this->interval->count - 1;
        } else {
            // If this is a full cycle then we need at least
            // 1 iteration so the generated timestamp is always
            // after the start date.
            // i.e. 1 month for monthly, 3 months for quarterly.
            // Ensures at least 1 iteration so the calculated timestamp
            // is always after the start date.
            $minBoundaries = $this->interval->count;
        }
        $boundaryMetric = self::$boundaryMetrics[$this->interval->interval];

        // Calculates next timestamp by adding days until we've
        // reached the target Nth day of week, month, or year.
        $next = $start;
        $boundariesCrossed = 0;
        while ($boundariesCrossed < $minBoundaries || !$this->isNthDay($n, $next)) {
            $lastBoundary = $next->format($boundaryMetric);
            $next = $next->addDay();
            $nextBoundary = $next->format($boundaryMetric);

            if ($nextBoundary != $lastBoundary) {
                ++$boundariesCrossed;
            }
        }

        // snap calculated timestamp to start of day
        return $next->setTime(0, 0);
    }

    /**
     * Calculates the previous timestamp that is snapped to the
     * given Nth day. The resulting timestamp will be the start of the day.
     *
     * The target Nth day can be in these ranges for a given interval:
     * - Week: 1 (for Monday) through 7 (for Sunday)
     * - Month: 1 - 31
     * - Year: 1 - 365
     *
     * @param int $n target Nth day
     *
     * @throws InvalidArgumentException if the input is invalid
     */
    public function prev(int $n, CarbonImmutable $end): CarbonImmutable
    {
        $this->validate($n);

        // Ensures that the generated timestamp crosses the appropriate
        // number of boundaries. The boundary is the interval used, either
        // week, month, or year. This is needed when the interval count is > 1.
        // Without this quarterly billing would only snap to the next month instead
        // of 3 months in the future.
        $isPartialCycle = !$this->isNthDay($n, $end);
        if ($isPartialCycle) {
            // If this is a partial cycle (i.e. the customer signed up mid-cycle)
            // then we want a boundary constraint of N - 1 because
            // the billing cycle should ALWAYS be less
            // than the full interval (1 month, 1 quarter, 1 year, etc).
            $minBoundaries = $this->interval->count - 1;
        } else {
            // If this is a full cycle then we need at least
            // 1 iteration so the generated timestamp is always
            // after the start date.
            // i.e. 1 month for monthly, 3 months for quarterly.
            // Ensures at least 1 iteration so the calculated timestamp
            // is always after the start date.
            $minBoundaries = $this->interval->count;
        }
        $boundaryMetric = self::$boundaryMetrics[$this->interval->interval];

        // Calculates next timestamp by adding days until we've
        // reached the target Nth day of week, month, or year.
        $previous = $end;
        $boundariesCrossed = 0;
        while ($boundariesCrossed < $minBoundaries || !$this->isNthDay($n, $previous)) {
            $lastBoundary = $previous->format($boundaryMetric);
            $previous = $previous->subDay();
            $previousBoundary = $previous->format($boundaryMetric);

            if ($previousBoundary != $lastBoundary) {
                ++$boundariesCrossed;
            }
        }

        // snap calculated timestamp to start of day
        return $previous->setTime(0, 0);
    }
}
