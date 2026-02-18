<?php

namespace App\Reports\ReportBuilder\ValueObjects;

final class Table
{
    public readonly string $alias;

    public function __construct(
        public readonly string $object,
        string $alias = '',
    ) {
        $this->alias = $alias ?: $this->object;
    }
}
