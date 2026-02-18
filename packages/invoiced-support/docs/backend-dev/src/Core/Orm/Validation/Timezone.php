<?php

namespace App\Core\Orm\Validation;

use DateTimeZone;
use Exception;
use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates a PHP time zone identifier.
 */
class Timezone implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        try {
            $tz = new DateTimeZone($value);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
