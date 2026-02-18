<?php

namespace App\SubscriptionBilling\Enums;

enum MrrMovementType: int
{
    case NewBusiness = 1;
    case Expansion = 2;
    case Contraction = 3;
    case Lost = 4;
    case Reactivation = 5;
}
