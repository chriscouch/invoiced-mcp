<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Canceled = 'canceled';
    case Complete = 'complete';
    case MissingInfo = 'missing_info';
    case Open = 'open';
}
