<?php

namespace App\Core\Ledger\Enums;

enum AccountType: int
{
    case Asset = 1;
    case Liability = 2;
    case Equity = 3;
    case Revenue = 4;
    case Expense = 5;
}
