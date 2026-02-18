<?php

namespace App\PaymentProcessing\Exceptions;

use Exception;

class InvalidBankAccountException extends Exception
{
    private string $param;

    public function __construct(string $message, string $param)
    {
        parent::__construct($message);
        $this->param = $param;
    }

    /**
     * Gets the param.
     */
    public function getParam(): string
    {
        return $this->param;
    }
}
