<?php

namespace App\PaymentProcessing\Exceptions;

use App\PaymentProcessing\Models\Charge;
use Exception;

class ChargeDeclinedException extends Exception
{
    public function __construct(
        public readonly Charge $charge,
    ) {
        parent::__construct($charge->failure_message ?? 'Charge was declined.');
    }
}
