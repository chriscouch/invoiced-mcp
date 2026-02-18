<?php

namespace App\Integrations\EarthClassMail\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int      $id
 * @property string   $api_key
 * @property string   $api_key_enc
 * @property int      $inbox_id
 * @property int|null $last_retrieved_data_at
 */
class EarthClassMailAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'api_key_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'inbox_id' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'last_retrieved_data_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
        ];
    }

    /**
     * Sets the `api_key` property by encrypting it
     * and storing it on `api_key_enc`.
     *
     * @param string $token
     *
     * @return mixed token
     */
    protected function setApiKeyValue($token)
    {
        if ($token) {
            $this->api_key_enc = $token;
        }

        return $token;
    }

    /**
     * Gets the decrypted `api_key` property value.
     *
     * @param mixed $token current value
     *
     * @return mixed decrypted token
     */
    protected function getApiKeyValue($token)
    {
        if ($token || !$this->api_key_enc) {
            return $token;
        }

        return $this->api_key_enc;
    }
}
