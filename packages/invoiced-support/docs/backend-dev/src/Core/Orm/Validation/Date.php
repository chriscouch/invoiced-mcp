<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates a date string.
 */
class Date implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        return strtotime($value);
    }
}
