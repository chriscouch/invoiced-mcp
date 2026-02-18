<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;

final class InvoicedPayment
{
    public function __construct(
        public readonly Payment $payment,
        public readonly ?AccountingPaymentMapping $mapping,
    ) {
    }
}
