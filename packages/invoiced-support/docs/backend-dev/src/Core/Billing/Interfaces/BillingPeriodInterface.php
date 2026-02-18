<?php

namespace App\Core\Billing\Interfaces;

use Carbon\CarbonImmutable;

interface BillingPeriodInterface
{
    /**
     * Gets the identifier of the billing period.
     */
    public function getName(): string;

    /**
     * Gets the start datetime of this billing period.
     */
    public function getStart(): CarbonImmutable;

    /**
     * Gets the end datetime of this billing period.
     */
    public function getEnd(): CarbonImmutable;
}
