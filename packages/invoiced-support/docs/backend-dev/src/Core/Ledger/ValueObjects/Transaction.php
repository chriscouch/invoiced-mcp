<?php

namespace App\Core\Ledger\ValueObjects;

use Carbon\CarbonImmutable;

final class Transaction
{
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly string $currency,
        public readonly array $entries,
        public readonly string $description = '',
    ) {
    }
}
