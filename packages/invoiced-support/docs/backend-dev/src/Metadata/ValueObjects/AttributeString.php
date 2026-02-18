<?php

namespace App\Metadata\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use App\Metadata\Exception\MetadataStorageException;
use App\Core\Orm\Model;

class AttributeString extends Attribute
{
    protected string $value;

    public function setValue($value): void
    {
        if (is_object($value) || is_array($value)) {
            if ($value instanceof Model) {
                $this->value = (string) $value->id();

                return;
            }
            if ($value instanceof Money) {
                $this->value = (string) $value;

                return;
            }
            $value = json_encode($value);
            if (!$value) {
                throw new MetadataStorageException('Cannot serialize value');
            }
        }
        $this->value = (string) $value;
    }

    protected function getPostfix(): string
    {
        return 'StringValues';
    }

    /**
     * @param string[] $values
     */
    public function getWhereConditionsIn(array $values, string $operator): array
    {
        // Get a list of all string column values
        return [
            ['value', $operator, $values],
        ];
    }

    public function getValue()
    {
        return $this->value;
    }
}
