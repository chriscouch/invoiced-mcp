<?php

namespace App\AccountsPayable\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use JsonSerializable;
use NumberFormatter;

final class CheckPdfVariables implements JsonSerializable
{
    private float $amount;
    private string $amountText;

    public function __construct(Money $amount)
    {
        $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
        $this->amount = $amount->toDecimal();
        $dollars = floor($this->amount);
        $cents = $this->amount - $dollars;
        $amountText = $formatter->format($dollars)." $cents/100";
        $this->amountText = strlen($amountText) <= 57 ? $amountText : '';
    }

    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'amount_text' => $this->amountText,
        ];
    }
}
