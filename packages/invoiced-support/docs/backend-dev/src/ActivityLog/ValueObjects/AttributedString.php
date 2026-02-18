<?php

namespace App\ActivityLog\ValueObjects;

use App\ActivityLog\Interfaces\AttributedValueInterface;

final class AttributedString implements AttributedValueInterface
{
    public function __construct(
        public readonly AttributedValueInterface|string $value
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'string',
            'value' => $this->value,
        ];
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
