<?php

namespace App\Companies\Enums;

enum TaxIdType: int
{
    case EIN = 1;
    case SSN = 2;
}
