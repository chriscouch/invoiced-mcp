<?php

namespace App\PaymentProcessing\Exceptions;

use Exception;

class AutoPayException extends Exception
{
    const EXPECTED_FAILURE_CODE = 412;
}
