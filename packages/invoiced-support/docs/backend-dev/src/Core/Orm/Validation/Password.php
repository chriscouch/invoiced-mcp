<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;

/**
 * Validates a password and hashes the value using
 * password_hash().
 *
 * Options:
 * - min: minimum password length
 * - cost: desired cost used to generate hash
 */
class Password implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        $minimumPasswordLength = $options['min'] ?? 8;

        if (strlen($value) < $minimumPasswordLength) {
            return false;
        }

        $hashOptions = [];
        if (isset($options['cost'])) {
            $hashOptions['cost'] = $options['cost'];
        }

        $value = password_hash($value, PASSWORD_DEFAULT, $hashOptions);

        return true;
    }
}
