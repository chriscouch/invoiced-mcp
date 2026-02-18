<?php

namespace App\Core\Ledger\ValueObjects;

final class LedgerEntry
{
    public function __construct(
        public readonly string $account,
        public readonly EntryAmount $amount,
        public readonly ?AccountingParty $party = null,
        public readonly ?int $documentId = null,
    ) {
    }
}
