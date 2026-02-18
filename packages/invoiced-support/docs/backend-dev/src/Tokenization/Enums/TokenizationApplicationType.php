<?php

namespace App\Tokenization\Enums;

enum TokenizationApplicationType: int
{
    case ACH = 1;
    case CARD = 2;
}
