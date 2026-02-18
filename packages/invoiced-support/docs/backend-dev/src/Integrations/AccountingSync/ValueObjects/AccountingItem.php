<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\Enums\IntegrationType;

final readonly class AccountingItem extends AbstractAccountingRecord
{
    public function __construct(
        IntegrationType $integration,
        string $accountingId,
        public array $values,
    ) {
        parent::__construct($integration, $accountingId);
    }
}
