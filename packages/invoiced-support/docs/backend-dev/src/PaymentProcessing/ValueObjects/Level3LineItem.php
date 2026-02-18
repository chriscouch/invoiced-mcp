<?php

namespace App\PaymentProcessing\ValueObjects;

use App\Core\I18n\ValueObjects\Money;

final readonly class Level3LineItem
{
    public Money $total;

    public function __construct(
        public string $productCode,
        public string $description,
        public string $commodityCode,
        public float $quantity,
        public Money $unitCost,
        public string $unitOfMeasure,
        public Money $discount,
    ) {
        $subtotal = Money::fromDecimal($this->unitCost->currency, $this->unitCost->toDecimal() * $this->quantity);
        $this->total = $subtotal->subtract($this->discount);
    }
}
