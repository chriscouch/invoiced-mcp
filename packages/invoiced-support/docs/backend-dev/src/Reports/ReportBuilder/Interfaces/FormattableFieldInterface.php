<?php

namespace App\Reports\ReportBuilder\Interfaces;

use App\Reports\Enums\ColumnType;

interface FormattableFieldInterface
{
    public function getExpression(): ExpressionInterface;

    public function getAlias(): string;

    public function getType(): ?ColumnType;

    public function getUnit(): ?string;
}
