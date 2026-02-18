<?php

namespace App\Reports\ReportBuilder\ValueObjects;

final class ObjectReferenceValue
{
    public function __construct(
        private string $object,
        private string $id,
        private mixed $value
    ) {
    }

    public function getObject(): string
    {
        return $this->object;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
