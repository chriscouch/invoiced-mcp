<?php

namespace App\Core\Billing\ValueObjects;

use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\I18n\ValueObjects\Money;
use Carbon\CarbonImmutable;

final class BillingOneTimeItem
{
    public readonly string $name;

    public function __construct(
        public Money $price,
        public int $quantity = 1,
        public readonly ?string $description = null,
        public readonly ?UsageType $usageType = null,
        public readonly ?BillingInterval $billingInterval = null,
        public readonly ?CarbonImmutable $periodStart = null,
        public readonly ?CarbonImmutable $periodEnd = null,
        public readonly ?string $itemId = null,
    ) {
        if ($usageType && $billingInterval) {
            $this->name = $usageType->getFriendlyName().' '.$billingInterval->name;
        } else {
            $this->name = '';
        }
    }
}
