<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;

final class ConstantExpression implements ExpressionInterface
{
    public function __construct(
        public readonly string $value,
        public readonly bool $unsafe
    ) {
    }

    public function getName(): ?string
    {
        return $this->value;
    }

    public function getType(): ?ColumnType
    {
        return null;
    }

    public function getSelectAlias(): string
    {
        return 'formula';
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
