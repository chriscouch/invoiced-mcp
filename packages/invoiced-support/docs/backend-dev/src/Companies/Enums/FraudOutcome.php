<?php

namespace App\Companies\Enums;

enum FraudOutcome: string
{
    case Pass = 'PASS';
    case Warning = 'WARNING';
    case Block = 'BLOCK';
}
