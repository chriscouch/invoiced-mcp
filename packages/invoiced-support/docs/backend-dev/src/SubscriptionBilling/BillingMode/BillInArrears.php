<?php

namespace App\SubscriptionBilling\BillingMode;

use App\SubscriptionBilling\Interfaces\BillingModeInterface;
use Carbon\CarbonImmutable;

class BillInArrears implements BillingModeInterface
{
    public function billDateInNextPeriod(): bool
    {
        return false;
    }

    public function billDateForPeriod(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): CarbonImmutable
    {
        return $periodEnd;
    }

    public function changePeriodEnd(CarbonImmutable $periodEnd): CarbonImmutable
    {
        // The new billing date should be the end of
        // the current billing period.
        return $periodEnd;
    }
}
