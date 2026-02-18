<?php

namespace App\Automations\Enums;

enum AutomationActionType: int
{
    case CreateObject = 1;
    case ModifyPropertyValue = 2;
    case CopyPropertyValue = 3;
    case ClearPropertyValue = 4;
    case DeleteObject = 5;
    case SendEmail = 6;
    case SendInternalNotification = 7;
    case Webhook = 8;
    case Condition = 9;
    case SendDocument = 10;
    case PostToSlack = 11;
}
