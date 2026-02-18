<?php

namespace App\SubscriptionBilling\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * A value object to represent a specific billing period within
 * subscription lifecycle. Billing periods consist of a
 * start date, end date, and bill date.
 */
final class BillingPeriod
{
    public function __construct(
        public readonly ?CarbonImmutable $startDate,
        public readonly ?CarbonImmutable $endDate,
        public readonly ?CarbonImmutable $billDate,
    ) {
    }

    public static function fromTimestamps(?int $startDate, ?int $endDate, ?int $billDate): self
    {
        $startDate = $startDate ? CarbonImmutable::createFromTimestamp($startDate) : null;
        $endDate = $endDate ? CarbonImmutable::createFromTimestamp($endDate) : null;
        $billDate = $billDate ? CarbonImmutable::createFromTimestamp($billDate) : null;

        return new self($startDate, $endDate, $billDate);
    }

    public function getStartDateTimestamp(): ?int
    {
        return $this->startDate?->getTimestamp();
    }

    public function getEndDateTimestamp(): ?int
    {
        return $this->endDate?->getTimestamp();
    }

    public function getBillDateTimestamp(): ?int
    {
        return $this->billDate?->getTimestamp();
    }
}
