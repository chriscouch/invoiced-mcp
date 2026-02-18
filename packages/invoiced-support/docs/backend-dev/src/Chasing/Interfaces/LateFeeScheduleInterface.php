<?php

namespace App\Chasing\Interfaces;

use App\Companies\Models\Company;

interface LateFeeScheduleInterface
{
    public function tenant(): Company;

    public function getGracePeriod(): int;

    public function getRecurringDays(): int;

    public function getAmount(): float;

    public function isPercent(): bool;
}
