<?php

namespace App\Integrations\Adyen\Exception;

use Exception;

class AdyenReconciliationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $identifier,
    ) {
        parent::__construct($message);
    }
}
