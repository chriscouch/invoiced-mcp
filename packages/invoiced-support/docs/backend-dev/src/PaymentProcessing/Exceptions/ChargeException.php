<?php

namespace App\PaymentProcessing\Exceptions;

use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use Exception;

class ChargeException extends Exception
{
    /**
     * @param ChargeValueObject|null $charge optional failed charge
     */
    public function __construct(
        string $message = '',
        public readonly ?ChargeValueObject $charge = null,
        public readonly string $reasonCode = '',
    ) {
        parent::__construct($message);
    }
}
