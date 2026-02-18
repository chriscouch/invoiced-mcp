<?php

namespace App\Notifications\NotificationEmails;

class SubscriptionCanceled extends AbstractSubscriptionNotificationEmail
{
    protected function getSubject(): string
    {
        return 'Subscription has been canceled in customer portal';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/subscription-canceled-bulk';
        }

        return 'notifications/subscription-canceled';
    }
}
