<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\Enums\IntegrationType;

final readonly class AccountingObjectReference
{
    public string $description;

    public function __construct(
        public IntegrationType $integrationType,
        public string $objectType,
        public string $accountingId,
        public ?string $invoicedId = null,
        string $description = '',
    ) {
        $this->description = $description ?: $this->accountingId;
    }
}
