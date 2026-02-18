<?php

namespace App\Integrations\NetSuite\Exceptions;

use App\Integrations\AccountingSync\Models\ReconciliationError;

class NetSuiteReconciliationException extends \Exception
{
    public function __construct(string $message, private string $level = ReconciliationError::LEVEL_ERROR)
    {
        parent::__construct($message);
    }

    public function getLevel(): string
    {
        return $this->level;
    }
}
