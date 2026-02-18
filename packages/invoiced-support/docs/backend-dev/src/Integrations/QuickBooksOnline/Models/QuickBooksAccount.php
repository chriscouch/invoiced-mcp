<?php

namespace App\Integrations\QuickBooksOnline\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\OAuth\Traits\OAuthAccountTrait;
use Carbon\CarbonImmutable;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string $realm_id
 * @property string $access_token
 * @property string $access_token_enc
 * @property string $refresh_token
 * @property string $refresh_token_enc
 * @property int    $expires
 * @property int    $refresh_token_expires
 * @property string $name
 */
class QuickBooksAccount extends MultitenantModel implements OAuthAccountInterface
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
            'realm_id' => new Property(
                required: true,
            ),
            'access_token_enc' => new Property(
                type: Type::STRING,
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'refresh_token_enc' => new Property(
                type: Type::STRING,
                encrypted: true,
                in_array: false,
            ),
            'expires' => new Property(
                type: Type::DATE_UNIX,
                required: true,
            ),
            'refresh_token_expires' => new Property(
                type: Type::DATE_UNIX,
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
     * Sets the `refresh_token` property by encrypting it
     * and storing it on `refresh_token_enc`.
     *
     * @param string $token
     *
     * @return mixed token
     */
    protected function setRefreshTokenValue($token)
    {
        if ($token) {
            $this->refresh_token_enc = $token;
        }

        return $token;
    }

    /**
     * Gets the decrypted `refresh_token` property value.
     *
     * @param mixed $token current value
     *
     * @return mixed decrypted token
     */
    protected function getRefreshTokenValue($token)
    {
        if ($token || !$this->refresh_token_enc) {
            return $token;
        }

        return $this->refresh_token_enc;
    }

    public function getToken(): OAuthAccessToken
    {
        return new OAuthAccessToken(
            $this->access_token,
            CarbonImmutable::createFromTimestamp($this->expires),
            $this->refresh_token,
            CarbonImmutable::createFromTimestamp($this->refresh_token_expires),
        );
    }

    public function setToken(OAuthAccessToken $token): void
    {
        $this->access_token = $token->accessToken;
        $this->refresh_token = $token->refreshToken;
        $this->expires = $token->accessTokenExpiration->getTimestamp();
        $this->refresh_token_expires = $token->refreshTokenExpiration?->getTimestamp() ?? time() + 86400 * 180;
    }
}
