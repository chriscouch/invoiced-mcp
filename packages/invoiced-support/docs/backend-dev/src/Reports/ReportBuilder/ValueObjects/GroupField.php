<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\Interfaces\FormattableFieldInterface;

final class GroupField implements FormattableFieldInterface
{
    public function __construct(
        public readonly ExpressionInterface $expression,
        public readonly bool $ascending,
        public readonly bool $expanded,
        public readonly string $name = '',
        public readonly bool $fillMissingData = false,
    ) {
    }

    public function getDirection(): string
    {
        return $this->ascending ? '' : 'DESC';
    }

    public function getExpression(): ExpressionInterface
    {
        return $this->expression;
    }

    public function getAlias(): string
    {
        return 'group_'.$this->expression->getSelectAlias();
    }

    public function getType(): ?ColumnType
    {
        return $this->expression->getType();
    }

    public function getUnit(): ?string
    {
        return null;
    }
}
