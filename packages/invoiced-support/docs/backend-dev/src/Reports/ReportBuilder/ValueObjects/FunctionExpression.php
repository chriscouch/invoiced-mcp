<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use App\Reports\Enums\ColumnType;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;

final class FunctionExpression implements ExpressionInterface
{
    public function __construct(
        public readonly string $functionName,
        public readonly ExpressionList $arguments,
        private ?ColumnType $type = null
    ) {
    }

    public function getName(): ?string
    {
        return $this->functionName;
    }

    public function getType(): ?ColumnType
    {
        if (!$this->type) {
            // special case for round function
            if ('round' == $this->functionName) {
                return count($this->arguments) > 0 ? ColumnType::Float : ColumnType::Integer;
            }

            // special case for age_range function
            if ('age_range' == $this->functionName) {
                return $this->arguments[1]->getType();
            }

            // special case for first_value/last_value function
            if (in_array($this->functionName, ['first_value', 'last_value'])) {
                return $this->arguments[0]->getType();
            }

            // inherit type from arguments if there is no fixed return type for this function
            return $this->arguments->getType();
        }

        return $this->type;
    }

    public function getSelectAlias(): string
    {
        return 'function';
    }

    public function shouldSummarize(): bool
    {
        // Only these functions can be added in a total or subtotal
        return in_array($this->functionName, ['age_range', 'count', 'count_distinct', 'sum']);
    }
}
