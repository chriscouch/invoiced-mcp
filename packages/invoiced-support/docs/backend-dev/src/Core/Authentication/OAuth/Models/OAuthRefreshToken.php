<?php

namespace App\Core\Authentication\OAuth\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

/**
 * @property string           $identifier
 * @property string           $expires
 * @property OAuthAccessToken $access_token
 */
class OAuthRefreshToken extends Model implements RefreshTokenEntityInterface
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'identifier' => new Property(),
            'expires' => new Property(),
            'access_token' => new Property(
                belongs_to: OAuthAccessToken::class,
            ),
        ];
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        return new CarbonImmutable($this->expires);
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $this->expires = (new CarbonImmutable($dateTime))->toDateTimeString();
    }

    public function setAccessToken(AccessTokenEntityInterface $accessToken): void
    {
        if ($accessToken instanceof OAuthAccessToken) {
            $this->access_token = $accessToken;
        }
    }

    public function getAccessToken(): AccessTokenEntityInterface
    {
        return $this->access_token;
    }
}
