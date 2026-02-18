<?php

namespace App\PaymentProcessing\Enums;

use App\Core\Ledger\Ledger;

enum MerchantAccountLedgerAccounts: string
{
    case Payments = 'Processed Payments';
    case Refunds = 'Refunded Payments';
    case Disputes = 'Disputed Payments';
    case MerchantAccount = 'Merchant Account';
    case BankAccount = 'Bank Account';
    case ProcessingFees = 'Processing Fees';
    case RoundingDifference = Ledger::ROUNDING_ACCOUNT;
}
