<?php

namespace App\CashApplication\Enums;

enum RemittanceAdviceException: int
{
    case DocumentDoesNotExist = 1;
    case DisputeDetected = 2;
    case DiscountTooHigh = 3;
    case DiscountExpired = 4;
}
