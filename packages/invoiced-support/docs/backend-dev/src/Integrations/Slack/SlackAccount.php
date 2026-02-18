<?php

namespace App\Integrations\Slack;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\OAuth\Traits\OAuthAccountTrait;
use Carbon\CarbonImmutable;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property string $team_id
 * @property string $name
 * @property string $access_token
 * @property string $access_token_enc
 * @property string $webhook_url
 * @property string $webhook_config_url
 * @property string $webhook_channel
 */
class SlackAccount extends MultitenantModel implements OAuthAccountInterface
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
            'team_id' => new Property(
                required: true,
            ),
            'name' => new Property(),
            'access_token_enc' => new Property(
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'webhook_url' => new Property(
                null: true,
            ),
            'webhook_config_url' => new Property(
                null: true,
            ),
            'webhook_channel' => new Property(
                null: true,
            ),
        ];
    }

    /**
     * Sets the `access_token` property by encrypting it
     * and storing it on `access_token_enc`.
     *
     * @param string $secret
     *
     * @return mixed token
     */
    protected function setAccessTokenValue($secret)
    {
        if ($secret) {
            $this->access_token_enc = $secret;
        }

        return $secret;
    }

    /**
     * Gets the decrypted `access_token` property value.
     *
     * @param mixed $secret current value
     *
     * @return mixed decrypted token
     */
    protected function getAccessTokenValue($secret)
    {
        if ($secret || !$this->access_token_enc) {
            return $secret;
        }

        return $this->access_token_enc;
    }

    public function getToken(): OAuthAccessToken
    {
        return new OAuthAccessToken(
            $this->access_token,
            new CarbonImmutable('2099-01-01'), // access tokens do not expire
            '', // refresh tokens not given
            null,
        );
    }

    public function setToken(OAuthAccessToken $token): void
    {
        $this->access_token = $token->accessToken;
    }
}
