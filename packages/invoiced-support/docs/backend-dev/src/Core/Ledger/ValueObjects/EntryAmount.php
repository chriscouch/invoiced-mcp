<?php

namespace App\Core\Ledger\ValueObjects;

use App\Core\Ledger\Enums\EntryType;
use App\Core\Ledger\Exception\LedgerException;

abstract class EntryAmount
{
    public readonly int $amountInCurrency;

    public function __construct(
        public readonly int $amount,
        public readonly EntryType $type,
        ?int $amountInCurrency,
    ) {
        if ($this->amount < 0) {
            throw new LedgerException('Amount cannot be negative');
        }

        $this->amountInCurrency = $amountInCurrency ?? $this->amount;
        if ($this->amountInCurrency < 0) {
            throw new LedgerException('Transaction amount cannot be negative');
        }
    }
}
