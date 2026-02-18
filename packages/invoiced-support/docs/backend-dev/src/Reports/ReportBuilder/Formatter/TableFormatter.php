<?php

namespace App\Reports\ReportBuilder\Formatter;

use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\FormattableFieldInterface;
use App\Reports\ReportBuilder\Interfaces\FormatterInterface;
use App\Reports\ReportBuilder\Interfaces\SectionInterface;
use App\Reports\ReportBuilder\Interfaces\TableSectionInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\GroupField;
use App\Reports\ReportBuilder\ValueObjects\ObjectReferenceValue;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use App\Reports\ValueObjects\NestedTableGroup;
use App\Reports\ValueObjects\Section;

/**
 * Formats report data into a table given the configuration.
 */
final class TableFormatter implements FormatterInterface
{
    /**
     * @param TableSectionInterface $section
     */
    public function format(SectionInterface $section, array $data, array $parameters): Section
    {
        $company = $section->getCompany();
        $valueFormatter = ValueFormatter::forCompany($company);
        $dataQuery = $section->getDataQuery();
        $fields = $dataQuery->fields->columns;

        // Build the table
        $columns = [];
        foreach ($fields as $field) {
            $columns[] = [
                'name' => $field->name,
                'type' => $field->type->value,
            ];
        }

        // Group data using every expanded group field
        $expandedGroups = $dataQuery->groupBy->getExpandedFields();
        $groupedRows = $this->buildGroup($expandedGroups, $company, $valueFormatter, $fields, $data, $parameters);

        // Add the grouped data to the table
        return (new Section($section->getTitle()))
            ->addGroup($this->addGroupToTable($columns, $company, $valueFormatter, $fields, $groupedRows, $parameters));
    }

    /**
     * @param GroupField[]   $groups
     * @param SelectColumn[] $fields
     */
    private function buildGroup(array $groups, Company $company, ValueFormatter $valueFormatter, array $fields, array $data, array $parameters, int $groupPointer = 0): array
    {
        // create blank footer row
        $footerRow = [];
        foreach ($fields as $field) {
            if (!$field->shouldSummarize) {
                $footerRow[] = '';
                continue;
            }

            if (in_array($field->type, [ColumnType::Float, ColumnType::Integer])) {
                $footerRow[] = 0;
            } elseif (ColumnType::Money == $field->type) {
                $footerRow[] = new Money($parameters['$currency'] ?? $company->currency, 0);
            } else {
                $footerRow[] = '';
            }
        }

        // recursively build all sub-groups
        if (isset($groups[$groupPointer])) {
            $groupField = $groups[$groupPointer];
            $group = $this->buildGroup($groups, $company, $valueFormatter, $fields, $data, $parameters, $groupPointer + 1);

            // group the results
            $groupMap = [];
            $rows = [];
            foreach ($group['rows'] as $row) {
                [$groupKey, $value] = $this->getGroupEntry($row, $groupField);
                if (!isset($groupMap[$groupKey])) {
                    $groupMap[$groupKey] = count($rows);
                    $value = $valueFormatter->format($company, $groupField, $value, $parameters);
                    $value ??= 'Empty';
                    $rows[] = [
                        'group' => [
                            'name' => $groupField->name,
                            'type' => $groupField->getType()?->value,
                            'value' => $value,
                        ],
                        'rows' => [],
                        'footer' => $footerRow,
                    ];
                }

                $index = $groupMap[$groupKey];
                $groupAlias = $groupField->getAlias();
                unset($row[$groupAlias]);

                // add detail row
                $rows[$index]['rows'][] = $row;

                // add to total
                $subTotalRow = $this->getTotalRow($row);
                foreach ($fields as $j => $field) {
                    if (!$field->shouldSummarize) {
                        continue;
                    }

                    $value = $subTotalRow[$j];
                    $value = $value instanceof ObjectReferenceValue ? $value->getValue() : $value;

                    if (in_array($field->type, [ColumnType::Float, ColumnType::Integer])) {
                        $rows[$index]['footer'][$j] += $value;
                    } elseif (ColumnType::Money == $field->type) {
                        $rows[$index]['footer'][$j] = $rows[$index]['footer'][$j]->add($value);
                    }
                }
            }
        } else {
            // base case
            $rows = $this->convertDataToRows($company, $fields, $groups, $data, $parameters);
        }

        // build total row
        foreach ($rows as $row) {
            $subTotalRow = $row;
            if (isset($subTotalRow['footer'])) {
                $subTotalRow = $subTotalRow['footer'];
            }

            foreach ($fields as $i => $field) {
                if (!$field->shouldSummarize) {
                    continue;
                }

                $value = $subTotalRow[$i];
                $value = $value instanceof ObjectReferenceValue ? $value->getValue() : $value;
                if (in_array($field->type, [ColumnType::Float, ColumnType::Integer])) {
                    $footerRow[$i] += $value;
                } elseif (ColumnType::Money == $field->type) {
                    $footerRow[$i] = $footerRow[$i]->add($value);
                }
            }
        }

        return [
            'rows' => $rows,
            'footer' => $footerRow,
        ];
    }

    /**
     * @param SelectColumn[] $fields
     * @param GroupField[]   $groups
     */
    private function convertDataToRows(Company $company, array $fields, array $groups, array $data, array $parameters): array
    {
        $rows = [];
        foreach ($data as $dataRow) {
            $row = [];
            foreach ($fields as $field) {
                $row[] = $this->convertDataToValue($company, $field, $dataRow, $parameters);
            }

            foreach ($groups as $groupField) {
                $row[$groupField->getAlias()] = $this->convertDataToValue($company, $groupField, $dataRow, $parameters);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function convertDataToValue(Company $company, FormattableFieldInterface $field, array $dataRow, array $parameters): mixed
    {
        // Convert value to proper data type, as needed
        $value = $dataRow[$field->getAlias()];
        if (ColumnType::Float == $field->getType()) {
            $value = (float) $value;
        } elseif (ColumnType::Integer == $field->getType()) {
            $value = (int) $value;
        } elseif (ColumnType::Money == $field->getType()) {
            $value = Money::fromDecimal($parameters['$currency'] ?? $company->currency, (float) $value);
        }

        // Attempt to set a reference to an object
        $expression = $field->getExpression();
        if ($expression instanceof FieldReferenceExpression) {
            $referenceColumn = str_replace(['.', '-'], ['_', '_'], $expression->table->alias.'_reference');
            $reference = $dataRow[$referenceColumn] ?? null;
            if ($reference) {
                $value = new ObjectReferenceValue($expression->table->object, $reference, $value);
            }
        }

        return $value;
    }

    /**
     * @param SelectColumn[] $fields
     */
    private function addGroupToTable(array $columns, Company $company, ValueFormatter $valueFormatter, array $fields, array $group, array $parameters): NestedTableGroup
    {
        $table = new NestedTableGroup($columns);

        // set the group name
        if (isset($group['group'])) {
            $table->setGroupHeader($group['group']);
        }

        // set the header
        if (isset($group['header'])) {
            foreach ($group['header'] as $i => &$value) {
                $field = $fields[$i];
                if ('' !== $value) {
                    $value = $valueFormatter->format($company, $field, $value, $parameters);
                }
            }

            $table->setHeader($group['header']);
        }

        // set the rows
        foreach ($group['rows'] as $row) {
            if (isset($row['rows'])) {
                $table->addRow($this->addGroupToTable($columns, $company, $valueFormatter, $fields, $row, $parameters));
            } else {
                // format the value in each cell
                foreach ($row as $i => &$value) {
                    $field = $fields[$i];
                    $value = $valueFormatter->format($company, $field, $value, $parameters);
                }

                $table->addRow($row);
            }
        }

        // set the footer
        $emptyFooter = true;
        foreach ($group['footer'] as $i => &$value) {
            $field = $fields[$i];
            if ('' !== $value) {
                $value = $valueFormatter->format($company, $field, $value, $parameters);
                $emptyFooter = false;
            }
        }
        if (!$emptyFooter) {
            $table->setFooter($group['footer']);
        }

        return $table;
    }

    private function getGroupEntry(array &$row, GroupField $groupField): array
    {
        if (isset($row['rows'])) {
            $value = null;
            $alias = $groupField->getAlias();
            foreach ($row['rows'] as &$nestedRow) {
                if (isset($nestedRow[$alias])) {
                    if (!$value) {
                        $value = $this->getGroupEntry($nestedRow, $groupField);
                    }

                    unset($nestedRow[$alias]);
                }
            }

            if (!$value) {
                return $this->getGroupEntry($row['rows'][0], $groupField);
            }

            return $value;
        }

        $groupKey = $row[$groupField->getAlias()];
        if ($groupKey instanceof ObjectReferenceValue) {
            $refValue = (string) $groupKey->getValue();
            if (!$refValue) {
                return ['__none__', ''];
            }

            return [$refValue, $groupKey];
        } elseif ($groupKey) {
            return [(string) $groupKey, $groupKey];
        }

        return ['__none__', ''];
    }

    private function getTotalRow(array $row): array
    {
        if (isset($row['footer'])) {
            return $row['footer'];
        }

        return $row;
    }
}
