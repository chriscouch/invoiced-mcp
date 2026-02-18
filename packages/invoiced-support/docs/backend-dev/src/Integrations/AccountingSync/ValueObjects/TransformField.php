<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\AccountingSync\Enums\TransformFieldType;

final class TransformField
{
    const VALUE_ID = '__value__';

    public function __construct(
        public readonly string $sourceField,
        public readonly string $destinationField,
        public readonly TransformFieldType $type = TransformFieldType::String,
        public readonly string $nullBehavior = 'ignore',
        public readonly int $timeOfDay = 0,
        public readonly bool $xmlEmptyAsNull = true,
        public readonly bool $documentContext = true,
        public readonly ?string $value = null,
    ) {
    }
}
