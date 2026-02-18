<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use Countable;

final class Filter implements Countable
{
    /**
     * @param FilterCondition[] $conditions
     */
    public function __construct(public readonly array $conditions)
    {
    }

    public function count(): int
    {
        // look recursively to pick up AND/OR nested conditions
        $n = 0;
        foreach ($this->conditions as $condition) {
            $n += $condition->count();
        }

        return $n;
    }
}
