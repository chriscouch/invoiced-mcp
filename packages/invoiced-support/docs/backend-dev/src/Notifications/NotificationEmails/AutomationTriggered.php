<?php

namespace App\Notifications\NotificationEmails;

class AutomationTriggered extends AbstractNotificationEmail
{
    protected function getSubject(): string
    {
        return 'Automation event was triggered';
    }

    public function getTemplate(array $events): string
    {
        return 'notifications/automation-triggered';
    }

    public function getVariables(array $events): array
    {
        return [
            'messages' => array_map(fn ($event) => $event->message, $events),
        ];
    }
}
