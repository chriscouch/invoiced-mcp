<?php

namespace App\Core\Ledger\Enums;

enum DocumentType
{
    case Adjustment;
    case Chargeback;
    case ChargebackReversal;
    case CreditBalanceAdjustment;
    case CreditBalanceApplication;
    case CreditNote;
    case Estimate;
    case Fee;
    case Invoice;
    case JournalEntry;
    case LateFee;
    case Order;
    case Payment;
    case Payout;
    case PayoutReversal;
    case Refund;
}
