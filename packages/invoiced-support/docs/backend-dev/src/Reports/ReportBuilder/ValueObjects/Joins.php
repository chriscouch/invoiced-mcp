<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use Countable;

final class Joins implements Countable
{
    /**
     * @param JoinCondition[] $conditions
     */
    public function __construct(public readonly array $conditions)
    {
    }

    public function count(): int
    {
        return count($this->conditions);
    }
}
