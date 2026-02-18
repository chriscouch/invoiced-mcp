<?php

namespace App\Reports\ReportBuilder;

use App\Reports\ReportBuilder\Formatter\ChartFormatter;
use App\Reports\ReportBuilder\Formatter\MetricFormatter;
use App\Reports\ReportBuilder\Formatter\MissingValueFiller;
use App\Reports\ReportBuilder\Formatter\TableFormatter;
use App\Reports\ReportBuilder\Interfaces\ChartSectionInterface;
use App\Reports\ReportBuilder\Interfaces\FormatterInterface;
use App\Reports\ReportBuilder\Interfaces\SectionInterface;
use App\Reports\ReportBuilder\Interfaces\TableSectionInterface;
use App\Reports\ReportBuilder\ValueObjects\Definition;
use App\Reports\ReportBuilder\ValueObjects\MetricReportSection;
use App\Reports\ValueObjects\Report;
use InvalidArgumentException;

/**
 * Converts the data fetched from the reporting service
 * into a finished report.
 */
final class Formatter
{
    public function __construct(
        private ChartFormatter $chartFormatter,
        private MetricFormatter $metricFormatter,
        private TableFormatter $tableFormatter,
    ) {
    }

    /**
     * Generates a report given its configuration and the data that was fetched
     * that can be displayed to the user or downloaded.
     *
     * @param array[] $sectionData
     */
    public function format(Definition $definition, array $parameters, array $sectionData): Report
    {
        $company = $definition->getCompany();
        $report = new Report($company);
        $report->setTitle($definition->getTitle());
        $report->setDefinition($definition);
        $report->setParameters($parameters);

        $dateFormat = $company->date_format;
        $filename = $definition->getTitle().' '.date($dateFormat);
        $filename = str_replace([' ', '/'], ['-', '-'], $filename);
        $report->setFilename($filename);

        foreach ($definition->getSections() as $k => $section) {
            $formatter = $this->getFormatter($section);
            $data = MissingValueFiller::fillMissingValues($section->getDataQuery(), $sectionData[$k], $parameters);
            $report->addSection($formatter->format($section, $data, $parameters));
        }

        return $report;
    }

    private function getFormatter(SectionInterface $section): FormatterInterface
    {
        if ($section instanceof ChartSectionInterface) {
            return $this->chartFormatter;
        }

        if ($section instanceof MetricReportSection) {
            return $this->metricFormatter;
        }

        if ($section instanceof TableSectionInterface) {
            return $this->tableFormatter;
        }

        throw new InvalidArgumentException('Section type not supported: '.get_class($section));
    }
}
