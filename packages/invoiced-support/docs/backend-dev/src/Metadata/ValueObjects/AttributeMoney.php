<?php

namespace App\Metadata\ValueObjects;

use App\Core\I18n\ValueObjects\Money;
use App\Metadata\Exception\MetadataStorageException;

class AttributeMoney extends Attribute
{
    protected string $currency;
    protected float $value;

    public function setValue($value): void
    {
        if (!is_object($value) && !is_array($value)) {
            throw new MetadataStorageException("Value {$value} doesn't validate as money type");
        }
        if ($value instanceof Money) {
            $this->currency = $value->currency;
            $this->value = $value->toDecimal();

            return;
        }

        $validationObject = (array) $value;
        if (!isset($validationObject['currency'])
            || !isset($validationObject['amount'])
            || !$validationObject['currency']
            || !is_numeric($validationObject['amount'])
        ) {
            $value = json_encode($value);
            throw new MetadataStorageException("Could not save metadata. Value {$value} doesn't validate as money type");
        }
        $this->currency = $validationObject['currency'];
        $this->value = (float) $validationObject['amount'];
    }

    protected function getPostfix(): string
    {
        return 'MoneyValues';
    }

    public function getInsertSQL(): string
    {
        return 'INSERT INTO '.$this->getTable().' (object_id, attribute_id, value, currency) VALUES (:objectId, :attributeId, :value, :currency) ON DUPLICATE KEY UPDATE value = VALUES(value),  currency = VALUES(currency)';
    }

    public function getSelectSQL(): string
    {
        return 'SELECT name,`type`,value, currency FROM '.$this->getTable().' v JOIN '.$this->getAttributeTable().' a ON v.attribute_id = a.id WHERE v.object_id = ?';
    }

    /**
     * @param array $row - row fetched from the DB
     *
     * @return array - key,value representing metadata
     */
    public function format(array $row): array
    {
        return [
            'key' => $row['name'],
            'value' => Money::fromDecimal($row['currency'], $row['value']),
        ];
    }

    public function getInsertParameters(): array
    {
        return [
            'attributeId' => $this->id,
            'objectId' => $this->model->id(),
            'value' => $this->value,
            'currency' => $this->currency,
        ];
    }

    public function getWhereConditions(string $operator): array
    {
        return [
            ['value', $operator, $this->value],
            ['currency', '=', $this->currency],
        ];
    }

    public function getValue()
    {
        return [
            'value' => $this->value,
            'currency' => $this->currency,
        ];
    }
}
