<?php

namespace App\EntryPoint\CronJob;

use App\Core\Utils\ValueObjects\Interval;

class SendMemberDailyUpdates extends SendMemberUpdates
{
    public static function getFrequency(): string
    {
        return Interval::DAY;
    }
}
