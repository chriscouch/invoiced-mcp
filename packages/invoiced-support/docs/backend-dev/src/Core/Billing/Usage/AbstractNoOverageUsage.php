<?php

namespace App\Core\Billing\Usage;

use App\Companies\Models\Company;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Interfaces\BillingPeriodInterface;
use App\Core\Billing\Interfaces\UsageInterface;
use App\Core\Billing\Models\AbstractUsageRecord;
use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\Models\UsagePricingPlan;

abstract class AbstractNoOverageUsage implements UsageInterface
{
    public function supports(BillingPeriodInterface $billingPeriod): bool
    {
        // This usage type is never billed as an overage and therefore supports no billing period
        return false;
    }

    public function canSendOverageNotification(): bool
    {
        // There should never be a notification about this usage type.
        return false;
    }

    public function calculateUsage(Company $company, BillingPeriodInterface $billingPeriod): AbstractUsageRecord
    {
        // This usage type is never billed as an overage and do not have usage records
        throw new BillingException('Calculating usage for this usage type is not supported.');
    }

    public function applyToCharge(UsagePricingPlan $pricingPlan, int $usage, OverageCharge $charge): void
    {
        // This usage type is never billed as an overage
    }
}
