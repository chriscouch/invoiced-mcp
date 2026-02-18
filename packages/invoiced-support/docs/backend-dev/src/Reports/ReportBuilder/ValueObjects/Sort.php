<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use Countable;

final class Sort implements Countable
{
    /**
     * @param SortField[] $fields
     */
    public function __construct(public readonly array $fields)
    {
    }

    public function count(): int
    {
        return count($this->fields);
    }
}
