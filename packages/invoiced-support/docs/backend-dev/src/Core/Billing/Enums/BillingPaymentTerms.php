<?php

namespace App\Core\Billing\Enums;

enum BillingPaymentTerms: int
{
    case AutoPay = 1;
    case Net30 = 2;
    case None = 3;
}
