<?php

namespace App\Companies\Enums;

enum PhoneVerificationChannel: int
{
    case Sms = 1;
    case Call = 2;
}
