<?php

namespace App\Core\Billing\BillingSystem;

use App\Core\Billing\Interfaces\BillingSystemInterface;
use App\Core\Billing\Models\BillingProfile;

class BillingSystemFactory
{
    public function __construct(
        private InvoicedBillingSystem $invoiced,
        private NullBillingSystem $default,
        private StripeBillingSystem $stripe,
        private ResellerBillingSystem $reseller
    ) {
    }

    public function getForBillingProfile(BillingProfile $billingProfile): BillingSystemInterface
    {
        return $this->get($billingProfile->billing_system);
    }

    public function get(?string $id): BillingSystemInterface
    {
        return match ($id) {
            InvoicedBillingSystem::ID => $this->invoiced,
            ResellerBillingSystem::ID => $this->reseller,
            StripeBillingSystem::ID => $this->stripe,
            default => $this->default,
        };
    }
}
