<?php

namespace App\Integrations\Twilio;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property string $account_sid
 * @property string $auth_token
 * @property string $auth_token_enc
 * @property string $from_number
 */
class TwilioAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'account_sid' => new Property(
                required: true,
            ),
            'auth_token_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'from_number' => new Property(),
        ];
    }

    /**
     * Sets the `auth_token` property by encrypting it
     * and storing it on `auth_token_enc`.
     *
     * @param string $secret
     *
     * @return mixed token
     */
    protected function setAuthTokenValue($secret)
    {
        if ($secret) {
            $this->auth_token_enc = $secret;
        }

        return $secret;
    }

    /**
     * Gets the decrypted `auth_token` property value.
     *
     * @param mixed $secret current value
     *
     * @return mixed decrypted token
     */
    protected function getAuthTokenValue($secret)
    {
        if ($secret || !$this->auth_token_enc) {
            return $secret;
        }

        return $this->auth_token_enc;
    }
}
