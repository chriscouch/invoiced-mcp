<?php

namespace App\EntryPoint\CronJob;

use App\Notifications\Enums\NotificationFrequency;

class NotificationEventWeeklyJob extends AbstractNotificationEventJob
{
    public static function getName(): string
    {
        return 'notification_events_weekly';
    }

    protected function getFrequency(): NotificationFrequency
    {
        return NotificationFrequency::Weekly;
    }
}
