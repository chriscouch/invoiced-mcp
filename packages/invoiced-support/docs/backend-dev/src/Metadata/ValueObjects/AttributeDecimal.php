<?php

namespace App\Metadata\ValueObjects;

use App\Metadata\Exception\MetadataStorageException;

class AttributeDecimal extends Attribute
{
    private float $value;

    public function setValue($value): void
    {
        if (!is_numeric($value) && !is_bool($value)) {
            throw new MetadataStorageException('Could not save metadata. Value '.json_encode($value)." doesn't validate as decimal");
        }
        $this->value = (float) $value;
    }

    /**
     * @return float|int|bool
     */
    public function getValue()
    {
        return (float) $this->formatValue($this->value);
    }

    /**
     * @param float|int|bool $input
     *
     * @return float
     */
    protected function formatValue($input)
    {
        return (float) $input;
    }

    protected function getPostfix(): string
    {
        return 'DecimalValues';
    }
}
