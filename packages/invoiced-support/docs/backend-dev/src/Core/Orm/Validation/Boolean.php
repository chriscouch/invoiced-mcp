<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates a boolean value.
 */
class Boolean implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return true;
    }
}
