<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates an e-mail address.
 */
class Email implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        $value = trim(strtolower($value));

        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
