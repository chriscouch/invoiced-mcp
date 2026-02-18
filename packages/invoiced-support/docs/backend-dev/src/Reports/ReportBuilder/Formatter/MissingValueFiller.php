<?php

namespace App\Reports\ReportBuilder\Formatter;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use Carbon\CarbonImmutable;
use Exception;

/**
 * This class is responsible for filling in missing values in a data set.
 * Currently only works with date values.
 */
class MissingValueFiller
{
    /**
     * When grouping by day, week, month, quarter, or year
     * and a date range is provided, then this function will fill
     * in any missing gaps in the data set. The group by field
     * must also have fill missing data enabled.
     */
    public static function fillMissingValues(DataQuery $query, array $data, array $parameters): array
    {
        if (!count($query->groupBy)) {
            return $data;
        }

        if (!isset($parameters['$dateRange'])) {
            return $data;
        }

        $groupByDate = $query->groupBy->fields[0];
        $dateType = $groupByDate->getType();
        if (!$groupByDate->fillMissingData || !$dateType || !in_array($dateType, [ColumnType::Day, ColumnType::Week, ColumnType::Month, ColumnType::Quarter, ColumnType::Year])) {
            return $data;
        }

        $groupKey = $groupByDate->getAlias();
        $rowKeys = [];
        $rowKeysWithSameType = [];
        foreach ($query->fields->columns as $field) {
            $rowKeys[] = $field->alias;
            if ($field->getType() == $dateType) {
                $rowKeysWithSameType[] = $field->alias;
            }
        }
        foreach ($query->groupBy->fields as $field) {
            $alias = $field->getAlias();
            $rowKeys[] = $alias;
            if ($field->getType() == $dateType) {
                $rowKeysWithSameType[] = $alias;
            }
        }

        try {
            /** @var CarbonImmutable $startDate */
            $startDate = CarbonImmutable::createFromFormat('Y-m-d', $parameters['$dateRange']['start']);
            /** @var CarbonImmutable $endDate */
            $endDate = CarbonImmutable::createFromFormat('Y-m-d', $parameters['$dateRange']['end']);

            // Generate the list of dates in correctly sorted order
            $comparisonCheck = $groupByDate->ascending ? 'lessThanOrEqualTo' : 'greaterThanOrEqualTo';
            $date = $groupByDate->ascending ? $startDate : $endDate;
            $stopDate = $groupByDate->ascending ? $endDate : $startDate;
            $rowIndex = 0;
            while ($date->$comparisonCheck($stopDate)) {
                // Check if present in data at expected position
                // and add row if not present
                $expectedValue = self::formatDate($date, $dateType);
                $rowValue = $data[$rowIndex][$groupKey] ?? null;
                if ($rowValue != $expectedValue) {
                    $newRow = array_fill_keys($rowKeys, null);
                    // Set all fields of the same type to the expected value
                    // This might not be accurate if there are multiple fields
                    // with this type that have different values.
                    foreach ($rowKeysWithSameType as $dateKey) {
                        $newRow[$dateKey] = $expectedValue;
                    }
                    array_splice($data, $rowIndex, 0, [$newRow]);
                }

                $date = self::incrementDatePeriod($date, $dateType, $groupByDate->ascending);
                ++$rowIndex;
            }
        } catch (Exception) {
            // Intentionally not throwing an exception here
        }

        return $data;
    }

    private static function incrementDatePeriod(CarbonImmutable $date, ColumnType $type, bool $ascending): CarbonImmutable
    {
        if (ColumnType::Day == $type) {
            return $ascending ? $date->addDay() : $date->subDay();
        }

        if (ColumnType::Week == $type) {
            return $ascending ? $date->addWeek() : $date->subWeek();
        }

        if (ColumnType::Month == $type) {
            return $ascending ? $date->addMonthNoOverflow() : $date->subMonthNoOverflow();
        }

        if (ColumnType::Quarter == $type) {
            return $ascending ? $date->addQuarterNoOverflow() : $date->subQuarterNoOverflow();
        }

        if (ColumnType::Year == $type) {
            return $ascending ? $date->addYearNoOverflow() : $date->subYearNoOverflow();
        }

        return $date;
    }

    /**
     * Formats a date to match the database format.
     */
    private static function formatDate(CarbonImmutable $date, ColumnType $type): string
    {
        if (ColumnType::Week == $type) {
            return $date->format('Y-W');
        }

        if (ColumnType::Month == $type) {
            return $date->format('Ym');
        }

        if (ColumnType::Quarter == $type) {
            return $date->format('Y').'Q'.ceil($date->month / 3);
        }

        if (ColumnType::Year == $type) {
            return $date->format('Y');
        }

        return $date->format('Y-m-d');
    }
}
