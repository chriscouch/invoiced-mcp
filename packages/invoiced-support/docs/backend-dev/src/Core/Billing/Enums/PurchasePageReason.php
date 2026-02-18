<?php

namespace App\Core\Billing\Enums;

enum PurchasePageReason: int
{
    case NewCompany = 1;
    case Activate = 2;
    case Upgrade = 3;
    case Reactivate = 4;
}
