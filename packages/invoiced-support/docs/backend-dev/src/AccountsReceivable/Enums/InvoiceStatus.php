<?php

namespace App\AccountsReceivable\Enums;

enum InvoiceStatus: string
{
    case BadDebt = 'bad_debt';
    case Disputed = 'disputed';
    case Draft = 'draft';
    case NotSent = 'not_sent';
    case Paid = 'paid';
    case PastDue = 'past_due';
    case Pending = 'pending';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Voided = 'voided';
}
