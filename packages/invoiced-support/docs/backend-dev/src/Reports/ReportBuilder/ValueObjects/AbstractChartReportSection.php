<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Companies\Models\Company;
use App\Reports\ReportBuilder\Interfaces\ChartSectionInterface;

abstract class AbstractChartReportSection extends AbstractReportSection implements ChartSectionInterface
{
    private array $chartOptions;

    public function __construct(string $title, DataQuery $dataQuery, Company $company, array $chartOptions)
    {
        parent::__construct($title, $dataQuery, $company);
        $this->chartOptions = $chartOptions;
    }

    public function getChartOptions(): array
    {
        return $this->chartOptions;
    }
}
