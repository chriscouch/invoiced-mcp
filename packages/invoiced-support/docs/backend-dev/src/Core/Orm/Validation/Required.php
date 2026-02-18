<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Makes sure that a variable is not empty.
 */
class Required implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        return !empty($value);
    }
}
