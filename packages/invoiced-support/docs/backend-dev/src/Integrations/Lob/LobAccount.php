<?php

namespace App\Integrations\Lob;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string      $key
 * @property string      $key_enc
 * @property bool        $return_envelopes
 * @property string|null $custom_envelope
 * @property bool        $use_color
 */
class LobAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'key_enc' => new Property(
                required: true,
                in_array: false,
            ),
            'return_envelopes' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'custom_envelope' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'use_color' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }

    /**
     * Sets the `key` property by encrypting it
     * and storing it on `key_enc`.
     *
     * @param string $secret
     *
     * @return mixed token
     */
    protected function setKeyValue($secret)
    {
        if ($secret) {
            $this->key_enc = $secret;
        }

        return $secret;
    }

    /**
     * Gets the decrypted `key` property value.
     *
     * @param mixed $secret current value
     *
     * @return mixed decrypted token
     */
    protected function getKeyValue($secret)
    {
        if ($secret || !$this->key_enc) {
            return $secret;
        }

        return $this->key_enc;
    }
}
