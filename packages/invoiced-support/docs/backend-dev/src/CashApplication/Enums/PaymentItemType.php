<?php

namespace App\CashApplication\Enums;

enum PaymentItemType: string
{
    case AppliedCredit = 'applied_credit';
    case ConvenienceFee = 'convenience_fee';
    case Credit = 'credit';
    case CreditNote = 'credit_note';
    case DocumentAdjustment = 'document_adjustment';
    case Estimate = 'estimate';
    case Invoice = 'invoice';
}
