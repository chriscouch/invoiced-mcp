<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates a URL.
 */
class Url implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_URL);
    }
}
