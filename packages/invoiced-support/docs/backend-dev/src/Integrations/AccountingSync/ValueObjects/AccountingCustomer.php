<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\Enums\IntegrationType;

final readonly class AccountingCustomer extends AbstractAccountingRecord
{
    public function __construct(
        IntegrationType $integration,
        string $accountingId,
        public array $values = [],
        public ?array $emails = null,
        public ?array $contacts = null,
        public self|null|false $parentCustomer = false,
        bool $deleted = false,
    ) {
        parent::__construct($integration, $accountingId, $deleted);
    }
}
