<?php

namespace App\ActivityLog\ValueObjects;

use App\Core\I18n\MoneyFormatter;
use App\ActivityLog\Interfaces\AttributedValueInterface;

final class AttributedMoneyAmount implements AttributedValueInterface
{
    public function __construct(
        public readonly string $currency,
        public readonly float $amount,
        public readonly array $moneyFormat,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'money',
            'currency' => $this->currency,
            'amount' => $this->amount,
        ];
    }

    public function __toString(): string
    {
        return MoneyFormatter::get()->currencyFormat(
            $this->amount,
            $this->currency,
            $this->moneyFormat,
        );
    }
}
