<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Converts a Unix timestamp into a format compatible with database
 * timestamp types.
 */
class MySqlDatetime implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        if (is_integer($value)) {
            // MySQL datetime format
            $value = date('Y-m-d H:i:s', $value);

            return true;
        }

        return false;
    }
}
