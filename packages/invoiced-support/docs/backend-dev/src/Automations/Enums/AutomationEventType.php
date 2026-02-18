<?php

namespace App\Automations\Enums;

enum AutomationEventType: string
{
    // we start events from 10000 to avoid conflicts with other activity log types
    case ReceivedEmail = 'inbox_email.received';

    public function toInteger(): int
    {
        return match ($this) {
            self::ReceivedEmail => 10000,
        };
    }
}
