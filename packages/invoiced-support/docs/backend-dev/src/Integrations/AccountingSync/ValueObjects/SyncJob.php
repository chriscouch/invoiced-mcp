<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use function array_value;

final class SyncJob
{
    public function __construct(private array $values)
    {
    }

    public function __get(string $k): mixed
    {
        return array_value($this->values, $k);
    }

    public function toArray(): array
    {
        return $this->values;
    }
}
