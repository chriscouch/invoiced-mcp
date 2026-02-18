<?php

namespace App\ActivityLog\ValueObjects;

use App\ActivityLog\Interfaces\AttributedValueInterface;

final readonly class AttributedObject implements AttributedValueInterface
{
    public function __construct(
        public string $type,
        public string|AttributedValueInterface $value,
        public mixed $id,
    ) {
    }

    public function jsonSerialize(): array
    {
        if (is_array($this->id)) {
            return array_merge([
                'type' => $this->type,
                'value' => $this->value,
            ], $this->id);
        }

        return [
            'type' => $this->type,
            'value' => $this->value,
            'id' => $this->id,
        ];
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
