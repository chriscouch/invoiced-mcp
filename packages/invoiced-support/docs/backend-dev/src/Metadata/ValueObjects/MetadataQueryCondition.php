<?php

namespace App\Metadata\ValueObjects;

class MetadataQueryCondition
{
    public function __construct(
        public readonly string $attributeName,
        public readonly mixed $value,
        public readonly string $operator = '=',
    ) {
    }
}
