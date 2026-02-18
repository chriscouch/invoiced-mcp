<?php

namespace App\Notifications\NotificationEmails;

class EstimateViewed extends AbstractEstimateNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Estimate was viewed in customer portal';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/estimate-viewed-bulk';
        }

        return 'notifications/estimate-viewed';
    }
}
