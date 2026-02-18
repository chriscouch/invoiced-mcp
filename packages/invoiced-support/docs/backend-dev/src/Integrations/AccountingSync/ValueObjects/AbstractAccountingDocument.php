<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\Enums\IntegrationType;

abstract readonly class AbstractAccountingDocument extends AbstractAccountingRecord
{
    public function __construct(
        IntegrationType $integration,
        string $accountingId,
        public ?AccountingCustomer $customer = null,
        public array $values = [],
        public bool $voided = false,
        bool $deleted = false,
        public ?string $pdf = null,
    ) {
        parent::__construct($integration, $accountingId, $deleted);
    }

    /**
     * Returns the PDF filename without the extension.
     */
    abstract public function getPdfFilename(): string;
}
