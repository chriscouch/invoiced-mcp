<?php

namespace App\Core\Orm\Validation;

use App\Core\Orm\Exception\ModelException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use App\Core\Orm\Interfaces\ValidationRuleInterface;
use App\Core\Orm\Model;
use App\Core\Orm\Type;

/**
 * Encrypts a string value using defuse/php-encryption.
 *
 * In order for this validation rule to work it requires
 * that the defuse/php-encryption library is installed and
 * that the encryption key has been set with Type::setEncryptionKey().
 */
class Encrypt implements ValidationRuleInterface
{
    public function validate(mixed &$value, array $options, Model $model): bool
    {
        // Encryption only works with strings. Convert to JSON if an object or array is given.
        if (is_object($value) || is_array($value)) {
            $value = json_encode($value);
        }

        $key = Type::getEncryptionKey();
        if (!$key instanceof Key) {
            throw new ModelException('Encryption key is not set');
        }
        $value = Crypto::encrypt($value, $key);

        return true;
    }
}
