<?php

namespace App\Reports\ReportBuilder\Formatter;

use App\Reports\Exceptions\ReportException;
use App\Reports\Libs\ChartBuilder;
use App\Reports\ReportBuilder\Interfaces\ChartSectionInterface;
use App\Reports\ReportBuilder\Interfaces\FormatterInterface;
use App\Reports\ReportBuilder\Interfaces\SectionInterface;
use App\Reports\ReportBuilder\ValueObjects\BarChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\DoughnutChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\LineChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\PieChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\PolarChartReportSection;
use App\Reports\ReportBuilder\ValueObjects\RadarChartReportSection;
use App\Reports\ValueObjects\Section;

/**
 * Formats report data into a chart given the configuration.
 */
final class ChartFormatter implements FormatterInterface
{
    public function __construct(
        private ChartBuilder $builder,
    ) {
    }

    /**
     * @param ChartSectionInterface $section
     */
    public function format(SectionInterface $section, array $data, array $parameters): Section
    {
        $company = $section->getCompany();
        $fields = $section->getDataQuery()->fields->columns;

        $group = match (get_class($section)) {
            BarChartReportSection::class => $this->builder->makeBarChart($company, $fields, $data, $parameters, $section->getChartOptions()),
            DoughnutChartReportSection::class => $this->builder->makePieChart($company, $fields, $data, $parameters, $section->getChartOptions(), 'doughnut'),
            LineChartReportSection::class => $this->builder->makeLineChart($company, $fields, $data, $parameters, $section->getChartOptions()),
            PieChartReportSection::class => $this->builder->makePieChart($company, $fields, $data, $parameters, $section->getChartOptions(), 'pie'),
            PolarChartReportSection::class => $this->builder->makePieChart($company, $fields, $data, $parameters, $section->getChartOptions(), 'polarArea'),
            RadarChartReportSection::class => $this->builder->makeRadarChart($company, $fields, $data, $section->getChartOptions()),
            default => throw new ReportException('Unsupported chart type'),
        };

        $section = new Section($section->getTitle());
        $section->addGroup($group);

        return $section;
    }
}
