<?php

namespace App\Integrations\WePay\Enums;

use App\Core\Ledger\Ledger;

enum PayoutAccounts: string
{
    case Payments = 'Processed Payments';
    case Refunds = 'Refunded Payments';
    case Chargebacks = 'Disputed Payments';
    case MerchantAccount = 'Invoiced Payments Account';
    case BankAccount = 'Bank Account';
    case ProcessingFees = 'Processing Fees';
    case RoundingDifference = Ledger::ROUNDING_ACCOUNT;
}
