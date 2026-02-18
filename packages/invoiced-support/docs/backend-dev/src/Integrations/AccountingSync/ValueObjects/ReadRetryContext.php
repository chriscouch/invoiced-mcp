<?php

namespace App\Integrations\AccountingSync\ValueObjects;

final readonly class ReadRetryContext extends RetryContext
{
    public function __construct(array $data, int $errorId)
    {
        parent::__construct(true, $data, $errorId);
    }
}
