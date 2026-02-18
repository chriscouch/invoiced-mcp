<?php

namespace App\Notifications\NotificationEmails;

class SubscriptionExpired extends AbstractSubscriptionNotificationEmail
{
    protected function getSubject(): string
    {
        return 'Subscription has been canceled due to non payment';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/subscription-expired-bulk';
        }

        return 'notifications/subscription-expired';
    }
}
