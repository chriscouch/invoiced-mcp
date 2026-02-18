<?php

namespace App\Notifications\NotificationEmails;

class NullNotificationEmail extends AbstractNotificationEmail
{
    protected function getSubject(): string
    {
        return '';
    }

    public function getTemplate(array $events): string
    {
        return '';
    }

    public function getVariables(array $events): array
    {
        return [];
    }
}
