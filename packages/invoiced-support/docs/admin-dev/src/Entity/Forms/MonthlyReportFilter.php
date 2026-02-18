<?php

namespace App\Entity\Forms;

use App\Form\MonthlyReportType;
use Carbon\CarbonImmutable;

class MonthlyReportFilter
{
    private ?string $month = null;
    private CarbonImmutable $start;
    private CarbonImmutable $end;
    private string $metric;

    public function setMonth(string $month): void
    {
        date_default_timezone_set('America/Chicago');
        $this->month = $month;
        [$year, $month] = explode('-', $month);
        $this->start = CarbonImmutable::now()
            ->setDate((int) $year, (int) $month, 1)
            ->startOfDay();
        $this->end = CarbonImmutable::now()
            ->setDate((int) $year, (int) $month, 1)
            ->endOfMonth();
    }

    public function getMonth(): ?string
    {
        return $this->month;
    }

    public function toString(): string
    {
        return $this->start->format('F Y');
    }

    public function getStart(): CarbonImmutable
    {
        return $this->start;
    }

    public function getEnd(): CarbonImmutable
    {
        return $this->end;
    }

    public function getMetric(): string
    {
        return $this->metric;
    }

    public function setMetric(string $metric): void
    {
        $this->metric = $metric;
    }

    public function getName(): string
    {
        return array_search($this->metric, MonthlyReportType::REPORT_TYPES) ?: 'Unknown';
    }
}
