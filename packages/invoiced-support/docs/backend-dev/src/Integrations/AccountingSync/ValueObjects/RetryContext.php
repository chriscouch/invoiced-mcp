<?php

namespace App\Integrations\AccountingSync\ValueObjects;

abstract readonly class RetryContext
{
    public function __construct(
        public bool $fromAccountingSystem,
        public array $data,
        public int $errorId,
    ) {
    }
}
