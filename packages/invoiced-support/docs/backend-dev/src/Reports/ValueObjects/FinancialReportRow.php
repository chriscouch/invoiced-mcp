<?php

namespace App\Reports\ValueObjects;

final class FinancialReportRow
{
    private array $header = [];
    private array $rows = [];
    private array $summary = [];

    public function setHeader(array|string ...$header): void
    {
        $this->header = $header;
    }

    /**
     * @return array[]|string[]
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    public function addNestedRow(self $row): void
    {
        $this->rows[] = $row;
    }

    public function addValue(array|string ...$values): void
    {
        $this->rows[] = $values;
    }

    /**
     * @return array[]|self[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function setSummary(array|string ...$values): void
    {
        $this->summary = $values;
    }

    /**
     * @return array[]|string[]
     */
    public function getSummary(): array
    {
        return $this->summary;
    }
}
