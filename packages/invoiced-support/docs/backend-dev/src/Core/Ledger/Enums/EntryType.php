<?php

namespace App\Core\Ledger\Enums;

enum EntryType: string
{
    case DEBIT = 'D';
    case CREDIT = 'C';
}
