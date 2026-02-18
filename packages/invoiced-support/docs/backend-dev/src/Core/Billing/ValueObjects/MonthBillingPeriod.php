<?php

namespace App\Core\Billing\ValueObjects;

use App\Core\Billing\Interfaces\BillingPeriodInterface;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class MonthBillingPeriod implements BillingPeriodInterface
{
    private CarbonImmutable $start;
    private CarbonImmutable $end;

    /**
     * @param string $month month key, i.e. `201512` for December 2015
     */
    public function __construct(private string $month)
    {
        if (6 != strlen($this->month) || !is_numeric($this->month)) {
            throw new InvalidArgumentException('Invalid month: '.$this->month);
        }

        $this->start = CarbonImmutable::createFromDate($this->getYear(), $this->getMonth())->startOfMonth();
        $this->end = $this->start->endOfMonth();
    }

    public static function now(): self
    {
        return new self(date('Ym'));
    }

    /**
     * Gets the month for a given timestamp.
     */
    public static function fromTimestamp(int $timestamp): self
    {
        // A timestamp for September 28, 2015 would return "201509"
        return new self(date('Ym', $timestamp));
    }

    public function getName(): string
    {
        return $this->month;
    }

    public function getStart(): CarbonImmutable
    {
        return $this->start;
    }

    public function getEnd(): CarbonImmutable
    {
        return $this->end;
    }

    public function getMonth(): int
    {
        return (int) substr($this->month, 4, 2);
    }

    public function getYear(): int
    {
        return (int) substr($this->month, 0, 4);
    }
}
