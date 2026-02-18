<?php

namespace App\Core\Billing\ValueObjects;

use App\Core\Billing\Enums\BillingInterval;
use App\Core\I18n\ValueObjects\Money;

final readonly class BillingSystemSubscription
{
    public function __construct(
        public BillingInterval $billingInterval,
        public Money $total,
        public bool $paused,
    ) {
    }
}
