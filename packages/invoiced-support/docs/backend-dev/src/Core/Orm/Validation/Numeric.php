<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates a number.
 *
 * Options:
 * - type: specifies a PHP type to validate with is_* (defaults to numeric)
 */
class Numeric implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        if (!isset($options['type'])) {
            return is_numeric($value);
        }

        $check = 'is_'.$options['type'];
        if (!is_callable($check)) {
            throw new ModelException('Type not supported: '.$options['type']);
        }

        return $check($value);
    }
}
