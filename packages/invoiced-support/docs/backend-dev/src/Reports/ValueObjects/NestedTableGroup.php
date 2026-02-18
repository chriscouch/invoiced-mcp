<?php

namespace App\Reports\ValueObjects;

use RuntimeException;

final class NestedTableGroup extends AbstractGroup
{
    private ?array $groupHeader = null;
    private ?array $header = null;
    /** @var array[]|NestedTableGroup[] */
    private array $rows = [];
    private ?array $footer = null;
    private int $columnCount;

    public function __construct(private array $columns)
    {
        $this->columnCount = count($columns);
    }

    /**
     * @param array $value
     */
    public function setGroupHeader($value): void
    {
        $this->groupHeader = $value;
    }

    public function setHeader(array $header): void
    {
        if (count($header) != $this->columnCount) {
            throw new RuntimeException('Incorrect number of columns added to header. Expecting '.$this->columnCount.' columns and was given '.count($header).' columns.');
        }

        $this->header = $header;
    }

    /**
     * @param array|NestedTableGroup $row
     */
    public function addRow($row): void
    {
        if (is_array($row) && count($row) != $this->columnCount) {
            throw new RuntimeException('Incorrect number of columns added to row. Expecting '.$this->columnCount.' columns and was given '.count($row).' columns.');
        }

        $this->rows[] = $row;
    }

    /**
     * @param array[]|NestedTableGroup[] $rows
     */
    public function addRows(array $rows): void
    {
        foreach ($rows as $row) {
            $this->addRow($row);
        }
    }

    public function setFooter(array $footer): void
    {
        if (count($footer) != $this->columnCount) {
            throw new RuntimeException('Incorrect number of columns added to footer. Expecting '.$this->columnCount.' columns and was given '.count($footer).' columns.');
        }

        $this->footer = $footer;
    }

    public function getType(): string
    {
        return 'nested_table';
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getGroupHeader(): ?array
    {
        return $this->groupHeader;
    }

    public function getHeader(): ?array
    {
        return $this->header;
    }

    /**
     * @return NestedTableGroup[]|array[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getFooter(): ?array
    {
        return $this->footer;
    }
}
