<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates that a number falls within a range.
 *
 * Options:
 * - min: minimum value that is valid
 * - max: maximum value that is valid
 */
class Range implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        // check min
        if (isset($options['min']) && $value < $options['min']) {
            return false;
        }

        // check max
        if (isset($options['max']) && $value > $options['max']) {
            return false;
        }

        return true;
    }
}
