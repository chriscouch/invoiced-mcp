<?php

namespace App\Core\Ledger\ValueObjects;

use App\Core\Ledger\Enums\PartyType;

class AccountingParty
{
    public function __construct(
        public readonly PartyType $type,
        public readonly int $id,
    ) {
    }
}
