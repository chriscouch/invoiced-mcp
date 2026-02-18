<?php

namespace App\Core\Billing\Usage;

use App\Companies\Models\Company;
use App\Core\Billing\Interfaces\BillingPeriodInterface;
use App\Core\Billing\Interfaces\UsageInterface;
use App\Core\Billing\Models\AbstractUsageRecord;
use App\Core\Billing\Models\OverageCharge;
use App\Core\Billing\Models\UsagePricingPlan;
use App\Core\I18n\MoneyFormatter;

abstract class AbstractRecordBasedUsage implements UsageInterface
{
    abstract public function getUsageRecord(Company $company, BillingPeriodInterface $billingPeriod): AbstractUsageRecord;

    abstract protected function getCount(Company $company, BillingPeriodInterface $billingPeriod): int;

    public function calculateUsage(Company $company, BillingPeriodInterface $billingPeriod): AbstractUsageRecord
    {
        $usageRecord = $this->getUsageRecord($company, $billingPeriod);
        $usageRecord->count = $this->getCount($company, $billingPeriod);
        $usageRecord->saveOrFail();

        return $usageRecord;
    }

    public function applyToCharge(UsagePricingPlan $pricingPlan, int $usage, OverageCharge $charge): void
    {
        // Determine the quantity that is over threshold
        $charge->quantity = max(0, $usage - $pricingPlan->threshold);

        // Determine the price and total
        $charge->price = $pricingPlan->unit_price;
        $charge->total = MoneyFormatter::get()->round('usd', $charge->quantity * $charge->price);
    }
}
