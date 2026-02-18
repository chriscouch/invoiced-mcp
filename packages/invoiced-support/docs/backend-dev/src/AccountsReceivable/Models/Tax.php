<?php

namespace App\AccountsReceivable\Models;

use App\SalesTax\Models\TaxRate;

/**
 * This model represents a line item or subtotal tax.
 */
class Tax extends AppliedRate
{
    const RATE_MODEL = TaxRate::class;
}
