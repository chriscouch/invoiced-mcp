<?php

namespace App\Integrations\AccountingSync\ValueObjects;

final class InvoicedObjectReference
{
    public function __construct(
        private string $objectType,
        private string $invoicedId,
        private string $description = ''
    ) {
        if (!$this->description) {
            $this->description = $this->invoicedId;
        }
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getInvoicedId(): string
    {
        return $this->invoicedId;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
