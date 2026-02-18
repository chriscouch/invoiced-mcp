<?php

namespace App\EntryPoint\CronJob;

use App\Notifications\Enums\NotificationFrequency;

class NotificationEventDailyJob extends AbstractNotificationEventJob
{
    public static function getName(): string
    {
        return 'notification_events_daily';
    }

    protected function getFrequency(): NotificationFrequency
    {
        return NotificationFrequency::Daily;
    }
}
