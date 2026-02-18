<?php

namespace App\Metadata\ValueObjects;

use App\Metadata\Interfaces\MetadataModelInterface;

abstract class Attribute
{
    protected int $id;
    protected string $name;
    protected int $type;
    protected MetadataModelInterface $model;

    /**
     * @param string|float|int|object|array|bool|null $value
     */
    abstract public function setValue($value): void;

    /**
     * @return string|float|int|object|array|bool|null
     */
    abstract public function getValue();

    abstract protected function getPostfix(): string;

    public function getName(): string
    {
        return $this->name;
    }

    public function getTable(): string
    {
        return $this->model->getMetadataTablePrefix().$this->getPostfix();
    }

    public function getAttributeTable(): string
    {
        return $this->model->getMetadataTablePrefix().'Attributes';
    }

    public function set(int $id, string $name, int $type): void
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
    }

    public function getInsertSQL(): string
    {
        return 'INSERT INTO '.$this->getTable().' (object_id, attribute_id, value) VALUES (:objectId, :attributeId, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)';
    }

    public function getInsertParameters(): array
    {
        return [
            'attributeId' => $this->id,
            'objectId' => $this->model->id(),
            'value' => $this->getValue(),
        ];
    }

    public function getSelectSQL(): string
    {
        return 'SELECT name,`type`, value FROM '.$this->getTable().' v JOIN '.$this->getAttributeTable().' a ON v.attribute_id = a.id WHERE v.object_id = ?';
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
            'value' => $this->formatValue($row['value']),
        ];
    }

    /**
     * @param string|float|int|bool|null $input
     *
     * @return string|float|int|bool|null
     */
    protected function formatValue($input)
    {
        return $input;
    }

    public function setModel(MetadataModelInterface $model): void
    {
        $this->model = $model;
    }

    public function getWhereConditions(string $operator): array
    {
        return [
            ['value', $operator, $this->getValue()],
        ];
    }

    /**
     * @param string[] $values
     */
    public function getWhereConditionsIn(array $values, string $operator): array
    {
        // Only string-like columns can be used for IN conditions
        throw new \RuntimeException('Type does not support being used as an IN query condition: '.static::class);
    }

    public function getId(): int
    {
        return $this->id;
    }
}
