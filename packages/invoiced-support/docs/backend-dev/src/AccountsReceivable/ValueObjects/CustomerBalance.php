<?php

namespace App\AccountsReceivable\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use JsonSerializable;

final class CustomerBalance implements JsonSerializable
{
    public function __construct(
        public readonly string $currency,
        public readonly Money $totalOutstanding,
        public readonly Money $dueNow,
        public readonly bool $pastDue,
        public readonly Money $openCreditNotes,
        public readonly Money $availableCredits,
        public readonly array $history,
    ) {
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'total_outstanding' => $this->totalOutstanding->toDecimal(),
            'due_now' => $this->dueNow->toDecimal(),
            'past_due' => $this->pastDue,
            'open_credit_notes' => $this->openCreditNotes->toDecimal(),
            'available_credits' => $this->availableCredits->toDecimal(),
            'history' => $this->history,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
