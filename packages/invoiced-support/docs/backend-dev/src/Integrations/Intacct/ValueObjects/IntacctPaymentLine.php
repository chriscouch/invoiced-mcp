<?php

namespace App\Integrations\Intacct\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingDocument;

/**
 * A modified representation of Intacct Payment objects' lines (INVOICES, CREDITS).
 */
final class IntacctPaymentLine
{
    public function __construct(
        public readonly string $recordNo,
        private Money $amount,
        public readonly AbstractAccountingDocument $document,
    ) {
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function setAmount(Money $amount): void
    {
        $this->amount = $amount;
    }
}
