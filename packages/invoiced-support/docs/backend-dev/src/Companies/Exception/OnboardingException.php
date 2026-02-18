<?php

namespace App\Companies\Exception;

use Exception;

class OnboardingException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $field = null,
    ) {
        parent::__construct($message, 0, null);
    }
}
