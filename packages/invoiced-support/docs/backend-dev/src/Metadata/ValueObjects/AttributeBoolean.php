<?php

namespace App\Metadata\ValueObjects;

use App\Metadata\Exception\MetadataStorageException;

class AttributeBoolean extends AbstractAttributeInteger
{
    protected bool $value;

    public function setValue($value): void
    {
        if ('true' === $value) {
            $value = true;
        } elseif ('false' === $value) {
            $value = false;
        }
        if (!is_bool($value) && !(is_numeric($value) && (0 == $value || 1 == $value))) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            throw new MetadataStorageException("Could not save metadata. Value {$value} doesn't validate as boolean");
        }
        $this->value = (bool) $value;
    }

    public function getValue(): bool
    {
        return $this->value;
    }

    public function getWhereConditions(string $operator): array
    {
        return [
            ['value', $operator, (int) $this->getValue()],
        ];
    }

    protected function formatValue($input): bool
    {
        return (bool) $input;
    }
}
