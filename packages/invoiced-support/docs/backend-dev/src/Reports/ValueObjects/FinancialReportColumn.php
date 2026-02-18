<?php

namespace App\Reports\ValueObjects;

use App\Reports\Enums\ColumnType;

final class FinancialReportColumn
{
    public function __construct(private string $name, private ColumnType $type)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): ColumnType
    {
        return $this->type;
    }
}
