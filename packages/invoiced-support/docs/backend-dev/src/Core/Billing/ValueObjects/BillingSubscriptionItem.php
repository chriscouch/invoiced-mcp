<?php

namespace App\Core\Billing\ValueObjects;

use App\Core\Billing\Enums\BillingInterval;
use App\Core\Billing\Enums\UsageType;
use App\Core\Entitlements\Models\Product;
use App\Core\I18n\ValueObjects\Money;

final readonly class BillingSubscriptionItem
{
    public Money $total;
    public string $name;

    public function __construct(
        public Money $price,
        public BillingInterval $billingInterval,
        public ?UsageType $usageType = null,
        public ?Product $product = null,
        public int $quantity = 1,
        public string $description = '',
        public bool $customPricing = false,
    ) {
        $this->total = Money::fromDecimal($price->currency, $price->toDecimal() * $quantity);

        if ($product) {
            $this->name = $product->name.' '.$billingInterval->name;
        } elseif ($usageType) {
            $this->name = $usageType->getFriendlyName().' '.$billingInterval->name;
        } else {
            $this->name = 'Pay-As-You-Go '.$billingInterval->name;
        }
    }
}
