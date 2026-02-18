<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;

final class SortField
{
    public function __construct(
        public readonly ExpressionInterface $expression,
        private bool $ascending = true
    ) {
    }

    public function getDirection(): string
    {
        return $this->ascending ? '' : 'DESC';
    }
}
