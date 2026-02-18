<?php

namespace App\SubscriptionBilling\Interfaces;

use Carbon\CarbonImmutable;

/**
 * The billing mode is responsible for calculating the billing dates
 * for each subscription billing cycle.
 */
interface BillingModeInterface
{
    /**
     * Returns whether the bill date should be calculated in
     * the next billing period.
     */
    public function billDateInNextPeriod(): bool;

    /**
     * Gets the bill date within the given billing period.
     */
    public function billDateForPeriod(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): CarbonImmutable;

    /**
     * Calculates the next bill date based when changing
     * the period end date.
     */
    public function changePeriodEnd(CarbonImmutable $periodEnd): CarbonImmutable;
}
