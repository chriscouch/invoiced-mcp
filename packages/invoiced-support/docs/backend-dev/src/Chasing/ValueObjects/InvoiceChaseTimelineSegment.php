<?php

namespace App\Chasing\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Representation of a point (or line segment) on the InvoiceChaseTimeline.
 */
class InvoiceChaseTimelineSegment
{
    /**
     * @param CarbonImmutable[] $dates
     */
    public function __construct(private InvoiceChaseStep $step, private array $dates)
    {
    }

    public function getChaseStep(): InvoiceChaseStep
    {
        return $this->step;
    }

    public function getStartDate(): CarbonImmutable
    {
        return $this->dates[0];
    }

    public function getEndDate(): CarbonImmutable
    {
        return $this->dates[count($this->dates) - 1];
    }

    /**
     * @return CarbonImmutable[]
     */
    public function getDates(): array
    {
        return $this->dates;
    }

    public function copy(): self
    {
        return new InvoiceChaseTimelineSegment($this->step, $this->dates);
    }
}
