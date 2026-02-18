<?php

namespace App\Reports\ReportBuilder\Interfaces;

interface ChartSectionInterface extends SectionInterface
{
    public function getChartOptions(): array;
}
