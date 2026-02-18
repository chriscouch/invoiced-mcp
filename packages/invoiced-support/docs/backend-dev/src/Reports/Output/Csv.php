<?php

namespace App\Reports\Output;

use App\Core\Csv\CsvWriter;
use App\Reports\Interfaces\ReportOutputInterface;
use App\Reports\ValueObjects\ChartGroup;
use App\Reports\ValueObjects\FinancialReportGroup;
use App\Reports\ValueObjects\FinancialReportRow;
use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\MetricGroup;
use App\Reports\ValueObjects\NestedTableGroup;
use App\Reports\ValueObjects\Report;
use RuntimeException;

class Csv implements ReportOutputInterface
{
    public function generate(Report $report): string
    {
        $output = [];
        $output[] = [$report->getTitle()];

        // Report Parameters
        foreach ($report->getNamedParameters() as $parameter) {
            $output[] = [$parameter['name'], $parameter['value']];
        }

        // Report Date
        $timeFormat = $report->getCompany()->date_format.' g:i a';
        $output[] = ['Generated', $report->getTime()->format($timeFormat)];

        // Report Sections
        $sections = $report->getSections();
        foreach ($sections as $section) {
            $output[] = [''];
            if ($title = $section->getTitle()) {
                $output[] = [$title];
            }

            foreach ($section->getGroups() as $group) {
                if ($group instanceof ChartGroup) {
                    $this->writeChartGroup($group, $output);
                } elseif ($group instanceof KeyValueGroup) {
                    $this->writeKeyValueGroup($group, $output);
                } elseif ($group instanceof MetricGroup) {
                    $this->writeMetricGroup($group, $output);
                } elseif ($group instanceof NestedTableGroup) {
                    $this->writeNestedTableGroup($group, $output);
                } elseif ($group instanceof FinancialReportGroup) {
                    $this->writeFinancialReportGroup($group, $output);
                } else {
                    throw new RuntimeException('Unsupported group type: '.$group->getType());
                }
            }
        }

        unset($sections);
        $csv = fopen('php://output', 'w');
        if (!$csv) {
            return '';
        }

        ob_start();
        foreach ($output as $row) {
            CsvWriter::write($csv, $row);
        }
        fclose($csv);

        return (string) ob_get_clean();
    }

    private function writeChartGroup(ChartGroup $chart, array &$output): void
    {
        $data = $chart->getData();
        $output[] = array_merge(['Dimension'], $data['labels']);

        $datasets = $data['datasets'];
        foreach ($datasets as $dataset) {
            $output[] = array_merge(
                [$dataset['label']],
                $dataset['data']);
        }
    }

    private function writeKeyValueGroup(KeyValueGroup $keyValue, array &$output): void
    {
        foreach ($keyValue->getLines() as $line) {
            $output[] = [$line['name'], $line['value']['value'] ?? $line['value']];
        }
    }

    private function writeMetricGroup(MetricGroup $metrics, array &$output): void
    {
        foreach ($metrics->getMetrics() as $metrics) {
            $output[] = [$metrics['name'], $metrics['value']['value'] ?? $metrics['value']];
        }
    }

    private function writeNestedTableGroup(NestedTableGroup $table, array &$output, int $level = 0): void
    {
        // group header
        if ($groupName = $table->getGroupHeader()) {
            $headerRow = array_fill(0, max(0, $level - 1), '');
            $headerRow[] = $groupName['name'];
            $headerRow[] = $groupName['value']['value'] ?? $groupName['value'];
            $output[] = $headerRow;
        }

        // columns
        $columnsRow = array_fill(0, $level, '');
        foreach ($table->getColumns() as $column) {
            $columnsRow[] = $column['name'];
        }
        $output[] = $columnsRow;
        $columnRowIndex = array_key_last($output);

        // header
        if ($header = $table->getHeader()) {
            $headerRow = array_fill(0, $level, '');
            foreach ($header as $item) {
                $headerRow[] = $item['value'] ?? $item;
            }
            $output[] = $headerRow;
        }

        // body
        $hadNestedTable = false;
        foreach ($table->getRows() as $row) {
            if ($row instanceof NestedTableGroup) {
                $hadNestedTable = true;
                $this->writeNestedTableGroup($row, $output, $level + 1);
            } else {
                $outputRow = array_fill(0, $level, '');
                foreach ($row as $v) {
                    $outputRow[] = $v['value'] ?? $v;
                }

                $output[] = $outputRow;
            }
        }

        // Remove column row if there were nested groups
        if ($hadNestedTable) {
            unset($output[$columnRowIndex]);
        }

        // footer
        if ($footer = $table->getFooter()) {
            $footerRow = array_fill(0, $level, '');
            foreach ($footer as $item) {
                $footerRow[] = $item['value'] ?? $item;
            }
            $output[] = $footerRow;
        }

        $output[] = [];
    }

    private function writeFinancialReportGroup(FinancialReportGroup $financialReport, array &$output): void
    {
        $headerRow = [];
        foreach ($financialReport->getColumns() as $column) {
            $headerRow[] = $column->getName();
        }
        $output[] = $headerRow;

        foreach ($financialReport->getRows() as $row) {
            $this->writeFinancialReportRow($row, $output);
        }
    }

    private function writeFinancialReportRow(FinancialReportRow $row, array &$output, int $level = 0): void
    {
        $this->writeFinancialReportLine($row->getHeader(), $output, $level - 1);

        foreach ($row->getRows() as $nestedRow) {
            if ($nestedRow instanceof FinancialReportRow) {
                $this->writeFinancialReportRow($nestedRow, $output, $level + 1);
            } else {
                $this->writeFinancialReportLine($nestedRow, $output, $level);
            }
        }

        $this->writeFinancialReportLine($row->getSummary(), $output, $level - 1);
    }

    private function writeFinancialReportLine(array $line, array &$output, int $level): void
    {
        $level = max(0, $level);
        $outputRow = [];
        foreach ($line as $element) {
            $outputRow[] = str_repeat(' ', $level * 2).($element['value'] ?? $element);
        }
        if (count($outputRow) > 0) {
            $output[] = $outputRow;
        }
    }
}
