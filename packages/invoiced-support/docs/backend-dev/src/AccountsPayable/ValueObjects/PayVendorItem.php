<?php

namespace App\AccountsPayable\ValueObjects;

use App\AccountsPayable\Models\Bill;
use App\Core\I18n\ValueObjects\Money;

final class PayVendorItem
{
    public function __construct(
        public readonly Bill $bill,
        public readonly Money $amount,
    ) {
    }
}
