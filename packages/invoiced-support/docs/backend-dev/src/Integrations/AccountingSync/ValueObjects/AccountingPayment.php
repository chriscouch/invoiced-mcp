<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\CashApplication\Models\Payment;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Enums\IntegrationType;

/**
 * An immutable value object to represent a payment from an accounting system.
 */
final readonly class AccountingPayment extends AbstractAccountingRecord
{
    public string $currency;

    /**
     * @param AccountingPaymentItem[] $appliedTo
     */
    public function __construct(
        IntegrationType $integration,
        string $accountingId,
        public array $values = [],
        string $currency = '',
        public ?AccountingCustomer $customer = null,
        public array $appliedTo = [],
        public bool $voided = false,
        bool $deleted = false,
    ) {
        parent::__construct($integration, $accountingId, $deleted);
        if (!$currency && count($this->appliedTo) > 0) {
            $currency = $this->appliedTo[0]->amount->currency;
        }
        $this->currency = $currency;
    }

    public function getAmount(): Money
    {
        $total = new Money($this->currency, 0);
        foreach ($this->appliedTo as $split) {
            if (!in_array($split->type, Payment::NON_CASH_SPLIT_TYPES)) {
                $total = $total->add($split->amount);
            }
        }

        return $total;
    }
}
