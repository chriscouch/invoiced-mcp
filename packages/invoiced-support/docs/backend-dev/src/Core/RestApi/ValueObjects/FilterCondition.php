<?php

namespace App\Core\RestApi\ValueObjects;

use App\Core\RestApi\Enum\FilterOperator;

final class FilterCondition
{
    public function __construct(
        public readonly FilterOperator $operator,
        public readonly string $field,
        public readonly mixed $value,
    ) {
    }
}
