<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;

/**
 * An immutable value object to represent the application of
 * a portion of an accounting payment to an invoice.
 */
final readonly class AccountingPaymentItem
{
    public function __construct(
        public Money $amount,
        public string $type = PaymentItemType::Invoice->value,
        public ?AccountingInvoice $invoice = null,
        public ?AccountingCreditNote $creditNote = null,
        public string $documentType = '',
    ) {
    }
}
