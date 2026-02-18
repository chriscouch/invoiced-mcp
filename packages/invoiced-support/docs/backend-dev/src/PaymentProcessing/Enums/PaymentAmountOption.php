<?php

namespace App\PaymentProcessing\Enums;

enum PaymentAmountOption: int
{
    case PayInFull = 1;
    case PayPartial = 2;
    case PaymentPlan = 3;
    case ApplyCredit = 4;
    case AdvancePayment = 5;
}
