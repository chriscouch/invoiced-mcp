<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use Countable;

final class Fields implements Countable
{
    /**
     * @param SelectColumn[] $columns
     */
    public function __construct(
        public readonly array $columns
    ) {
    }

    public function count(): int
    {
        return count($this->columns);
    }
}
