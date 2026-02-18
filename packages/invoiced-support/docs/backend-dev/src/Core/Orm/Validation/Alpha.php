<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates an alpha string.
 *
 * Options:
 * - min: specifies a minimum length
 */
class Alpha implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        $minLength = $options['min'] ?? 0;

        return preg_match('/^[A-Za-z]*$/', $value) && strlen($value) >= $minLength;
    }
}
