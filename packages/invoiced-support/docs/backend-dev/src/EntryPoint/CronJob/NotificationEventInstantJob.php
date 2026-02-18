<?php

namespace App\EntryPoint\CronJob;

use App\Notifications\Enums\NotificationFrequency;

class NotificationEventInstantJob extends AbstractNotificationEventJob
{
    public static function getName(): string
    {
        return 'notification_events_instant';
    }

    public static function getLockTtl(): int
    {
        return 150; // 2.5 minutes
    }

    protected function getFrequency(): NotificationFrequency
    {
        return NotificationFrequency::Instant;
    }
}
