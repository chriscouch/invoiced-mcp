<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\Enums\IntegrationType;

abstract readonly class AbstractAccountingRecord
{
    public function __construct(
        public IntegrationType $integration,
        public string $accountingId,
        public bool $deleted = false,
    ) {
    }
}
