<?php

namespace App\SubscriptionBilling\Libs;

use App\SubscriptionBilling\Models\Subscription;
use Carbon\CarbonImmutable;

final class ContractPeriods
{
    public function __construct(private Subscription $subscription)
    {
        $this->subscription->tenant()->useTimezone();
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    /**
     * Updates the contract period on the subscription.
     */
    public function update(): void
    {
        if (!$this->subscription->contract_period_start) {
            $this->subscription->contract_period_start = $this->subscription->start_date;
        }

        if (!$this->subscription->contract_period_end) {
            $endDate = $this->endDate();
            $this->subscription->contract_period_end = ($endDate ? $endDate->getTimestamp() : null);
        }
    }

    /**
     * Advances the contract to the next term on the subscription.
     */
    public function advance(): void
    {
        $this->subscription->contract_period_start = $this->subscription->contract_period_end + 1;
        $endDate = $this->endDate();
        $this->subscription->contract_period_end = ($endDate ? $endDate->getTimestamp() : null);
    }

    /**
     * Computes the contract end date of the subscription.
     */
    public function endDate(): ?CarbonImmutable
    {
        $cycles = $this->subscription->cycles;
        if (!$cycles) {
            return null;
        }

        $endDate = CarbonImmutable::createFromTimestamp($this->subscription->contract_period_start ?: $this->subscription->start_date);
        for ($i = 0; $i < $cycles; ++$i) {
            $startDate = $i > 0 ? $endDate->addSecond() : $endDate;
            $endDate = BillingPeriods::calculatePeriodEnd($this->subscription, $startDate);
        }

        // subtract one second to prevent billing period overlap
        if (0 == $endDate->getTimestamp() % 2) {
            $endDate = $endDate->subSecond();
        }

        return $endDate;
    }
}
