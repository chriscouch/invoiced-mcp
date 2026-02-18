<?php

namespace App\SubscriptionBilling\ValueObjects;

use App\SubscriptionBilling\Models\MrrVersion;
use App\SubscriptionBilling\Models\Plan;
use Carbon\CarbonImmutable;
use Symfony\Component\Console\Output\OutputInterface;

final class MrrCalculationState
{
    /** @var Plan[] */
    private array $plans = [];
    private ?CarbonImmutable $earliestDate = null;
    private array $mrrByCustomer = [];

    public function __construct(
        public readonly MrrVersion $version,
        public readonly OutputInterface $output,
    ) {
    }

    public function hasPlan(int $id): bool
    {
        return isset($this->plans[$id]);
    }

    public function setPlan(int $id, Plan $plan): void
    {
        $this->plans[$id] = $plan;
    }

    public function getPlan(int $id): Plan
    {
        return $this->plans[$id];
    }

    public function setEarliestDate(CarbonImmutable $date): void
    {
        if (!$this->earliestDate || $date->isBefore($this->earliestDate)) {
            $this->earliestDate = $date;
        }
    }

    public function getEarliestDate(): ?CarbonImmutable
    {
        return $this->earliestDate;
    }

    public function hasMrrByCustomer(CarbonImmutable $month): bool
    {
        return isset($this->mrrByCustomer[$month->toDateString()]);
    }

    public function setMrrByCustomer(CarbonImmutable $month, array $value): void
    {
        $this->mrrByCustomer[$month->toDateString()] = $value;
    }

    public function getMrrByCustomer(CarbonImmutable $month): array
    {
        return $this->mrrByCustomer[$month->toDateString()];
    }
}
