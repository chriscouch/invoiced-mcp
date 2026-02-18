<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use Countable;

final class FilterCondition implements Countable, ExpressionInterface
{
    public function __construct(
        public readonly ?ExpressionInterface $field,
        public readonly string $operator,
        public readonly mixed $value
    ) {
    }

    public function count(): int
    {
        if (in_array($this->operator, ['and', 'or']) && is_array($this->value)) {
            $n = 0;
            foreach ($this->value as $subCondition) {
                $n += $subCondition->count();
            }

            return $n;
        }

        return 1;
    }

    public function getName(): ?string
    {
        return null;
    }

    public function getType(): ?ColumnType
    {
        return null;
    }

    public function getSelectAlias(): string
    {
        return 'formula';
    }
}
