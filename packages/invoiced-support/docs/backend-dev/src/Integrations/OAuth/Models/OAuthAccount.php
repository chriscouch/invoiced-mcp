<?php

namespace App\Integrations\OAuth\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\OAuth\Traits\OAuthAccountTrait;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int                    $id
 * @property IntegrationType        $integration
 * @property string                 $access_token
 * @property string                 $refresh_token
 * @property DateTimeInterface      $access_token_expiration
 * @property DateTimeInterface|null $refresh_token_expiration
 * @property string                 $name
 * @property object|null            $metadata
 */
class OAuthAccount extends MultitenantModel implements OAuthAccountInterface
{
    use AutoTimestamps;
    use OAuthAccountTrait;

    protected static function getProperties(): array
    {
        return [
            'integration' => new Property(
                type: Type::ENUM,
                required: true,
                enum_class: IntegrationType::class,
            ),
            'access_token' => new Property(
                type: Type::STRING,
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'refresh_token' => new Property(
                type: Type::STRING,
                encrypted: true,
                in_array: false,
            ),
            'access_token_expiration' => new Property(
                type: Type::DATETIME,
                required: true,
            ),
            'refresh_token_expiration' => new Property(
                type: Type::DATETIME,
                null: true,
            ),
            'name' => new Property(
                default: '',
            ),
            'metadata' => new Property(
                type: Type::OBJECT,
                null: true,
            ),
        ];
    }

    public function getToken(): OAuthAccessToken
    {
        return new OAuthAccessToken(
            $this->access_token,
            new CarbonImmutable($this->access_token_expiration),
            $this->refresh_token,
            $this->refresh_token_expiration ? new CarbonImmutable($this->refresh_token_expiration) : null,
        );
    }

    public function setToken(OAuthAccessToken $token): void
    {
        $this->access_token = $token->accessToken;
        $this->refresh_token = $token->refreshToken;
        $this->access_token_expiration = $token->accessTokenExpiration;
        $this->refresh_token_expiration = $token->refreshTokenExpiration;
    }

    public function addMetadata(string $key, string $value): void
    {
        $metadata = $this->metadata;
        if (!is_object($metadata)) {
            $metadata = (object) [];
        }

        $metadata->$key = $value;
        $this->metadata = $metadata;
    }

    public function getMetadata(string $key): mixed
    {
        $metadata = $this->metadata;
        if (!is_object($metadata)) {
            return null;
        }

        return $metadata->$key ?? null;
    }
}
