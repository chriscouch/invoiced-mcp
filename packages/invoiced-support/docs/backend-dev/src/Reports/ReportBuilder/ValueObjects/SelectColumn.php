<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\Interfaces\FormattableFieldInterface;

final class SelectColumn implements FormattableFieldInterface
{
    private static int $counter = 1;

    public readonly string $alias;

    public function __construct(
        public readonly ExpressionInterface $expression,
        public readonly string $name = '',
        public readonly ColumnType $type = ColumnType::String,
        public readonly ?string $unit = null,
        public readonly bool $shouldSummarize = false,
        public readonly bool $hideEmptyValues = false,
        string $alias = ''
    ) {
        if (!$alias) {
            $alias = $expression->getSelectAlias().'_'.self::$counter;
            ++self::$counter;
        }

        $this->alias = $alias;
    }

    public function getExpression(): ExpressionInterface
    {
        return $this->expression;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getType(): ?ColumnType
    {
        return $this->type;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public static function resetCounter(): void
    {
        self::$counter = 1;
    }
}
