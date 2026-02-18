<?php

namespace App\Automations\Enums;

enum AutomationResult: int
{
    case Pending = 1;
    case Succeeded = 2;
    case Failed = 3;
    case Stop = 4;
}
