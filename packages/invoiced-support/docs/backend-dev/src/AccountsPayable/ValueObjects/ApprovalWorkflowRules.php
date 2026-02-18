<?php

namespace App\AccountsPayable\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use JsonSerializable;

/**
 * @property Money  $available_credits
 * @property string $currency
 * @property array  $history
 * @property Money  $past_due
 * @property Money  $total_outstanding
 * @property Money  $open_credit_notes
 * @property Money  $due_now
 */
final class ApprovalWorkflowRules implements JsonSerializable
{
    public function __construct(private array $values)
    {
    }

    public function __get(string $k): mixed
    {
        return $this->values[$k];
    }

    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
