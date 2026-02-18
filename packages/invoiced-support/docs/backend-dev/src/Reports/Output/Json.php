<?php

namespace App\Reports\Output;

use App\Reports\Interfaces\ReportOutputInterface;
use App\Reports\ValueObjects\AbstractGroup;
use App\Reports\ValueObjects\ChartGroup;
use App\Reports\ValueObjects\FinancialReportColumn;
use App\Reports\ValueObjects\FinancialReportGroup;
use App\Reports\ValueObjects\FinancialReportRow;
use App\Reports\ValueObjects\KeyValueGroup;
use App\Reports\ValueObjects\MetricGroup;
use App\Reports\ValueObjects\NestedTableGroup;
use App\Reports\ValueObjects\Report;
use App\Reports\ValueObjects\Section;
use RuntimeException;

class Json implements ReportOutputInterface
{
    public function generate(Report $report): array
    {
        $sections = [];
        foreach ($report->getSections() as $section) {
            $sections[] = $this->buildSection($section);
        }

        return $sections;
    }

    public function buildSection(Section $section): array
    {
        $groups = [];
        foreach ($section->getGroups() as $group) {
            $groups[] = $this->buildGroup($group);
        }

        return [
            'title' => $section->getTitle(),
            'class' => $section->getClass(),
            'type' => 'section',
            'groups' => $groups,
        ];
    }

    public function buildGroup(AbstractGroup $group): array
    {
        if ($group instanceof ChartGroup) {
            return $this->outputChartGroup($group);
        }

        if ($group instanceof KeyValueGroup) {
            return $this->outputKeyValueGroup($group);
        }

        if ($group instanceof MetricGroup) {
            return $this->outputMetricGroup($group);
        }

        if ($group instanceof NestedTableGroup) {
            return $this->outputNestedTableGroup($group);
        }

        if ($group instanceof FinancialReportGroup) {
            return $this->outputFinancialReportGroup($group);
        }

        throw new RuntimeException('Group type not supported: '.$group->getType());
    }

    private function outputChartGroup(ChartGroup $group): array
    {
        return [
            'type' => $group->getType(),
            'chart_type' => $group->getChartType(),
            'data' => $group->getData(),
            'options' => (object) $group->getChartOptions(),
        ];
    }

    private function outputKeyValueGroup(KeyValueGroup $group): array
    {
        return [
            'type' => $group->getType(),
            'lines' => $group->getLines(),
        ];
    }

    private function outputMetricGroup(MetricGroup $group): array
    {
        return [
            'type' => $group->getType(),
            'metrics' => $group->getMetrics(),
        ];
    }

    private function outputNestedTableGroup(NestedTableGroup $group, bool $root = true): array
    {
        $rows = [];
        foreach ($group->getRows() as $row) {
            if (is_array($row)) {
                $rows[] = [
                    'type' => 'data',
                    'columns' => $row,
                ];
            } else {
                $rows[] = $this->outputNestedTableGroup($row, false);
            }
        }

        $result = [
            'type' => $group->getType(),
            'rows' => $rows,
        ];

        if ($root) {
            $result['columns'] = $group->getColumns();
        }

        if ($groupHeader = $group->getGroupHeader()) {
            $result['group'] = $groupHeader;
        }

        if ($header = $group->getHeader()) {
            $result['header'] = [
                'columns' => $header,
            ];
        }

        if ($footer = $group->getFooter()) {
            $result['footer'] = [
                'columns' => $footer,
            ];
        }

        return $result;
    }

    private function outputFinancialReportGroup(FinancialReportGroup $group): array
    {
        return [
            'type' => $group->getType(),
            'columns' => $this->outputFinancialReportColumns($group->getColumns()),
            'rows' => $this->outputFinancialReportRows($group->getRows()),
        ];
    }

    /**
     * @param FinancialReportColumn[] $columns
     */
    private function outputFinancialReportColumns(array $columns): array
    {
        $result = [];
        foreach ($columns as $column) {
            $result[] = [
                'name' => $column->getName(),
                'type' => $column->getType()->value,
            ];
        }

        return $result;
    }

    /**
     * @param FinancialReportRow[]|array[] $rows
     */
    private function outputFinancialReportRows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if ($row instanceof FinancialReportRow) {
                $result[] = [
                    'type' => 'financial_rows',
                    'header' => $row->getHeader(),
                    'rows' => $this->outputFinancialReportRows($row->getRows()),
                    'summary' => $row->getSummary(),
                ];
            } else {
                $result[] = [
                    'type' => 'data',
                    'columns' => $row,
                ];
            }
        }

        return $result;
    }
}
