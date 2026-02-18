<?php

namespace App\Automations\Enums;

enum AutomationTriggerType: int
{
    case Event = 1;
    case Schedule = 2;
    case Manual = 3;

    public function displayName(): string
    {
        return match ($this) {
            self::Event => 'on event',
            self::Schedule => 'on schedule',
            self::Manual => 'manually',
        };
    }
}
