<?php

namespace App\Core\Ledger\ValueObjects;

use App\Core\Ledger\Enums\DocumentType;
use Carbon\CarbonImmutable;

final class Document
{
    public function __construct(
        public readonly DocumentType $type,
        public readonly string $reference,
        public readonly AccountingParty $party,
        public readonly CarbonImmutable $date,
        public readonly ?CarbonImmutable $dueDate = null,
        public readonly ?string $number = null,
    ) {
    }
}
