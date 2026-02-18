<?php

namespace App\Core\Cron\ValueObjects;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use DateTimeZone;

class CronDate
{
    // Cron date calculations always happen in the UTC time zone. Any
    // jobs should be scheduled in UTC time.
    const TIME_ZONE = 'UTC';

    /**
     * Gets the calculated next run timestamp.
     */
    public function __construct(private string $schedule, private ?int $lastRan = null)
    {
    }

    public function getNextRun(): int
    {
        $cron = new CronExpression($this->schedule);

        return $cron->getNextRunDate(CarbonImmutable::createFromTimestamp($this->lastRan ?? time(), new DateTimeZone(self::TIME_ZONE)))->getTimestamp();
    }
}
