<?php

namespace App\Reports\ReportBuilder\Formatter;

use App\Reports\ReportBuilder\Interfaces\FormatterInterface;
use App\Reports\ReportBuilder\Interfaces\SectionInterface;
use App\Reports\ReportBuilder\ValueObjects\MetricReportSection;
use App\Reports\ValueObjects\MetricGroup;
use App\Reports\ValueObjects\Section;

final class MetricFormatter implements FormatterInterface
{
    /**
     * @param MetricReportSection $section
     */
    public function format(SectionInterface $section, array $data, array $parameters): Section
    {
        $fields = $section->getDataQuery()->fields->columns;

        $field = $fields[0];
        $value = $data[0][$field->alias];

        $valueFormatter = ValueFormatter::forCompany($section->getCompany());
        $value = $valueFormatter->format($section->getCompany(), $field, $value, $parameters);

        $group = new MetricGroup();
        $group->addMetric($field->name, $value);

        return (new Section($section->getTitle()))
            ->addGroup($group);
    }
}
