<?php

namespace App\Notifications\NotificationEmails;

class EstimateApproved extends AbstractEstimateNotificationEmail
{
    const THRESHOLD = 3;

    protected function getSubject(): string
    {
        return 'Estimate was approved';
    }

    public function getTemplate(array $events): string
    {
        if (count($events) > static::THRESHOLD) {
            return 'notifications/estimate-approved-bulk';
        }

        return 'notifications/estimate-approved';
    }
}
