<?php

namespace App\Core\Billing\Interfaces;

use App\Companies\Models\Company;
use App\Core\Billing\Exception\BillingException;
use App\Core\Billing\Models\AbstractUsageRecord;
use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\Models\UsagePricingPlan;

interface UsageInterface
{
    /**
     * Checks if this usage type supports the billing period.
     */
    public function supports(BillingPeriodInterface $billingPeriod): bool;

    /**
     * Calculates tenant usage within the given period and stores
     * into a usage record. This method can be called at the end of
     * the billing period or throughout the billing period in order
     * to give insight into usage.
     *
     * @throws BillingException
     */
    public function calculateUsage(Company $company, BillingPeriodInterface $billingPeriod): AbstractUsageRecord;

    /**
     * Indicates whether this usage type should send a notification
     * to the user when the usage pricing plan threshold is exceeded.
     */
    public function canSendOverageNotification(): bool;

    /**
     * Applies to the usage to a charge model. Depending on the pricing plan
     * this may not result in any amount owed.
     * The charge model SHOULD NOT be saved during this process.
     *
     * @throws BillingException
     */
    public function applyToCharge(UsagePricingPlan $pricingPlan, int $usage, OverageCharge $charge): void;
}
