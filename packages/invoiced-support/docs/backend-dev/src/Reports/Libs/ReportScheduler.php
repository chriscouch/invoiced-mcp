<?php

namespace App\Reports\Libs;

use App\Core\Utils\ValueObjects\Interval;
use App\Reports\Exceptions\ReportException;
use App\Reports\Models\ScheduledReport;
use App\SubscriptionBilling\Libs\DateSnapper;
use Carbon\CarbonImmutable;

final class ReportScheduler
{
    /**
     * @throws ReportException
     */
    public static function nextRun(ScheduledReport $scheduledReport): CarbonImmutable
    {
        // IMPORTANT! Make sure the next run is scheduled
        // in the merchant's native time zone
        $scheduledReport->tenant()->useTimezone();
        $nextRun = CarbonImmutable::now()->setTime($scheduledReport->time_of_day, 0, 0);
        $runDate = $scheduledReport->run_date;

        // if the calculated next run is not after the last run
        // or if it's in the past and has not run before then move
        // it to tomorrow
        if (ScheduledReport::FREQUENCY_DAY_OF_WEEK == $scheduledReport->frequency) {
            if ($runDate < 1 || $runDate > 7) {
                throw new ReportException('Run date must be between 1 and 7');
            }

            $snapper = new DateSnapper(new Interval(1, 'week'));
            $nextRun = $snapper->next($runDate, $nextRun);

            // the date snapper will set the time to 00:00
            // so the time needs to be set on the date
            return $nextRun->setTime($scheduledReport->time_of_day, 0, 0);
        }

        if (ScheduledReport::FREQUENCY_DAY_OF_MONTH == $scheduledReport->frequency) {
            if ($runDate < 1 || $runDate > 31) {
                throw new ReportException('Run date must be between 1 and 31');
            }

            $snapper = new DateSnapper(new Interval(1, 'month'));
            $nextRun = $snapper->next($runDate, $nextRun);

            // the date snapper will set the time to 00:00
            // so the time needs to be set on the date
            return $nextRun->setTime($scheduledReport->time_of_day, 0, 0);
        }

        throw new ReportException('Unsupported frequency: '.$scheduledReport->frequency);
    }
}
