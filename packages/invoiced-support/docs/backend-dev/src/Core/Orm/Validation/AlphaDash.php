<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates an alpha-numeric string with dashes and underscores.
 *
 * Options:
 * - min: specifies a minimum length
 */
class AlphaDash implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        $minLength = $options['min'] ?? 0;

        return preg_match('/^[A-Za-z0-9_-]*$/', $value) && strlen($value) >= $minLength;
    }
}
