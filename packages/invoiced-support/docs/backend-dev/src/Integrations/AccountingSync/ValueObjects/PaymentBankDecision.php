<?php

namespace App\Integrations\AccountingSync\ValueObjects;

/**
 * Represents the result of determining where to
 * route a payment into the accounting system.
 */
final readonly class PaymentBankDecision
{
    public function __construct(
        public bool $isUndepositedFunds,
        public string $account,
    ) {
    }
}
