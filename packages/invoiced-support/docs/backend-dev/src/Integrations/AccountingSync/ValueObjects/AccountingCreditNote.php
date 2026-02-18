<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Enums\IntegrationType;

final readonly class AccountingCreditNote extends AbstractAccountingDocument
{
    /**
     * @param AccountingPayment[] $payments
     */
    public function __construct(
        IntegrationType $integration,
        string $accountingId,
        ?AccountingCustomer $customer = null,
        array $values = [],
        bool $voided = false,
        ?string $pdf = null,
        public ?array $payments = null,
        bool $deleted = false,
        public ?Money $balance = null,
    ) {
        parent::__construct($integration, $accountingId, $customer, $values, $voided, $deleted, $pdf);
    }

    public function getPdfFilename(): string
    {
        return 'Credit Note';
    }
}
