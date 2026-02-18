<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Calls a custom validation function.
 *
 * Options:
 * - fn: specifies a callable value (required)
 */
class Callables implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        return $options['fn']($value, $options, $model);
    }
}
