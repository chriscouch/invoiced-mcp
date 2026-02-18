<?php

namespace App\AccountsReceivable\Enums;

enum DisputeStatus: int
{
    case Open = 1;
    case Accepted = 2;
    case Rejected = 3;
    case Canceled = 4;
}
