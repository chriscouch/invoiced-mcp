<?php

namespace App\AccountsPayable\Enums;

/**
 * The chart of accounts for the A/P subledger.
 */
enum ApAccounts: string
{
    case AccountsPayable = 'Accounts Payable';
    case Purchases = 'Purchases';
    case Cash = 'Cash';
    case ConvenienceFee = 'Convenience Fees';
}
