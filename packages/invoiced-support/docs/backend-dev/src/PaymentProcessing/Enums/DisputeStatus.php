<?php

namespace App\PaymentProcessing\Enums;

enum DisputeStatus: int
{
    case Accepted = 1;
    case Expired = 2;
    case Lost = 3;
    case Pending = 4;
    case Responded = 5;
    case Undefended = 6;
    case Unresponded = 7;
    case Won = 8;
}
