<?php

namespace App\SubscriptionBilling\BillingMode;

use App\SubscriptionBilling\Interfaces\BillingModeInterface;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BillInAdvance implements BillingModeInterface
{
    private int $billInAdvanceDays;

    public function __construct(int $billInAdvanceDays)
    {
        if ($billInAdvanceDays < 0) {
            throw new InvalidArgumentException('Bill in advance days cannot be negative');
        }

        $this->billInAdvanceDays = $billInAdvanceDays;
    }

    public function getBillInAdvanceDays(): int
    {
        return $this->billInAdvanceDays;
    }

    public function billDateInNextPeriod(): bool
    {
        return true;
    }

    public function billDateForPeriod(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): CarbonImmutable
    {
        return $this->getBillingDateForPeriodStart($periodStart);
    }

    public function changePeriodEnd(CarbonImmutable $periodEnd): CarbonImmutable
    {
        // The new billing date should be based on the
        // start of the NEXT billing period.
        return $this->getBillingDateForPeriodStart($periodEnd->addSecond());
    }

    private function getBillingDateForPeriodStart(CarbonImmutable $periodStart): CarbonImmutable
    {
        // If the bill in advance offset does not cause the bill date
        // to be in the past, then we want to apply it. Otherwise, we want
        // to take the earliest of the period start date or the start of today.
        $billDate = $periodStart->subDays($this->billInAdvanceDays);
        $currentTime = new CarbonImmutable();
        if ($billDate > $currentTime) {
            return $billDate;
        }

        // if non advanced bill date is in future, we move it to the beginning of today
        $beginningOfToday = $currentTime->setTime(0, 0);

        return $periodStart->greaterThan($beginningOfToday) ? $beginningOfToday : $periodStart;
    }
}
