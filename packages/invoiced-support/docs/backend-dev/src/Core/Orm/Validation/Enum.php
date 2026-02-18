<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates a value matches one of the available choices.
 *
 * Options:
 * - choices: specifies a list of valid choices (required)
 */
class Enum implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        return in_array($value, $options['choices']);
    }
}
