<?php

namespace App\Core\RestApi\ValueObjects;

final class ListFilter
{
    /**
     * @param FilterCondition[] $filters
     */
    public function __construct(
        public readonly array $filters
    ) {
    }

    public function with(FilterCondition $filter): self
    {
        return new self(array_merge($this->filters, [$filter]));
    }
}
