<?php

namespace App\Core\Ledger\ValueObjects;

use App\Core\Ledger\Enums\EntryType;

final class Credit extends EntryAmount
{
    public function __construct(int $amount, ?int $amountInCurrency = null)
    {
        parent::__construct($amount, EntryType::CREDIT, $amountInCurrency);
    }
}
