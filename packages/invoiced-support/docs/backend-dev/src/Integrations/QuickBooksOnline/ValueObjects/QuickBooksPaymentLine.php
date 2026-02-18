<?php

namespace App\Integrations\QuickBooksOnline\ValueObjects;

use App\Core\I18n\ValueObjects\Money;

/**
 * A modified representation of QBO Payment objects' Line item.
 */
final class QuickBooksPaymentLine
{
    public function __construct(
        public readonly array $document,
        private Money $amount,
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
