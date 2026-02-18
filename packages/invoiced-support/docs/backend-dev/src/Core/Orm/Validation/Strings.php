<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates a string.
 *
 * Options:
 * - min: specifies a minimum length
 * - max:  specifies a maximum length
 */
class Strings implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $len = strlen($value);
        $min = $options['min'] ?? 0;
        $max = $options['max'] ?? null;

        return $len >= $min && (!$max || $len <= $max);
    }
}
