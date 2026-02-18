<?php

namespace App\Reports\ValueObjects;

final class FinancialReportGroup extends AbstractGroup
{
    /**
     * @var FinancialReportRow[]
     */
    private array $rows = [];

    /**
     * @param FinancialReportColumn[] $columns
     */
    public function __construct(private array $columns)
    {
    }

    public function getType(): string
    {
        return 'financial';
    }

    /**
     * @return FinancialReportColumn[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return FinancialReportRow[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function addRow(FinancialReportRow $row): void
    {
        $this->rows[] = $row;
    }

    /**
     * @param FinancialReportRow[] $rows
     */
    public function addRows(array $rows): void
    {
        $this->rows = array_merge($this->rows, $rows);
    }
}
