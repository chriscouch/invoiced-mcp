<?php

namespace App\Integrations\Xero\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\OAuth\Traits\OAuthAccountTrait;
use Carbon\CarbonImmutable;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string $organization_id
 * @property string $access_token
 * @property string $access_token_enc
 * @property string $session_handle
 * @property string $session_handle_enc
 * @property int    $expires
 * @property string $name
 */
class XeroAccount extends MultitenantModel implements OAuthAccountInterface
{
    use AutoTimestamps;
    use OAuthAccountTrait;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'organization_id' => new Property(
                null: true,
            ),
            'access_token_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'session_handle_enc' => new Property(
                null: true,
                encrypted: true,
                in_array: false,
            ),
            'expires' => new Property(
                type: Type::DATE_UNIX,
                required: true,
            ),
            'name' => new Property(
                null: true,
            ),
        ];
    }

    /**
     * Sets the `access_token` property by encrypting it
     * and storing it on `access_token_enc`.
     *
     * @param string $token
     *
     * @return mixed token
     */
    protected function setAccessTokenValue($token)
    {
        if ($token) {
            $this->access_token_enc = $token;
        }

        return $token;
    }

    /**
     * Gets the decrypted `access_token` property value.
     *
     * @param mixed $token current value
     *
     * @return mixed decrypted token
     */
    protected function getAccessTokenValue($token)
    {
        if ($token || !$this->access_token_enc) {
            return $token;
        }

        return $this->access_token_enc;
    }

    /**
     * Sets the `session_handle` property by encrypting it
     * and storing it on `session_handle_enc`.
     *
     * @param string $secret
     *
     * @return mixed secret
     */
    protected function setSessionHandleValue($secret)
    {
        if ($secret) {
            $this->session_handle_enc = $secret;
        }

        return $secret;
    }

    /**
     * Gets the decrypted `session_handle` property value.
     *
     * @param mixed $secret current value
     *
     * @return mixed decrypted secret
     */
    protected function getSessionHandleValue($secret)
    {
        if ($secret || !$this->session_handle_enc) {
            return $secret;
        }

        return $this->session_handle_enc;
    }

    public function getToken(): OAuthAccessToken
    {
        return new OAuthAccessToken(
            $this->access_token,
            CarbonImmutable::createFromTimestamp($this->expires),
            $this->session_handle,
            null
        );
    }

    public function setToken(OAuthAccessToken $token): void
    {
        $this->access_token = $token->accessToken;
        $this->expires = $token->accessTokenExpiration->getTimestamp();
        $this->session_handle = $token->refreshToken;
    }
}
