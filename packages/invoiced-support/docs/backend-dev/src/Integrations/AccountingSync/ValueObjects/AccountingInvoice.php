<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Enums\IntegrationType;

final readonly class AccountingInvoice extends AbstractAccountingDocument
{
    public function __construct(
        IntegrationType $integration,
        string $accountingId,
        ?AccountingCustomer $customer = null,
        array $values = [],
        bool $voided = false,
        ?string $pdf = null,
        public array $installments = [],
        // DEPRECATED
        public array $contactList = [],
        // DEPRECATED
        public array $distributionSettings = [],
        bool $deleted = false,
        public array $delivery = [],
        public ?Money $balance = null,
    ) {
        parent::__construct($integration, $accountingId, $customer, $values, $voided, $deleted, $pdf);
    }

    public function getPdfFilename(): string
    {
        return 'Invoice';
    }
}
