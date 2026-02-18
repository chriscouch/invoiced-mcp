<?php

namespace App\Core\Billing\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class BillingState
{
    public function __construct(
        public array $paymentSource,
        public ?array $discount,
        public bool $cancelAtPeriodEnd,
        public ?CarbonImmutable $nextBillDate,
        public bool $autopay,
        public float $nextChargeAmount,
    ) {
    }
}
