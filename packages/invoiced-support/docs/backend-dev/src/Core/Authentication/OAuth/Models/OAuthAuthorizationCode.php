<?php

namespace App\Core\Authentication\OAuth\Models;

use App\Core\Authentication\OAuth\ValueObjects\OAuthScope;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * @property string           $identifier
 * @property string           $user_identifier
 * @property array            $scopes
 * @property string           $expires
 * @property OAuthApplication $application
 * @property string           $redirect_uri
 */
class OAuthAuthorizationCode extends Model implements AuthCodeEntityInterface
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'identifier' => new Property(),
            'user_identifier' => new Property(),
            'expires' => new Property(),
            'redirect_uri' => new Property(),
            'scopes' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'application' => new Property(
                belongs_to: OAuthApplication::class,
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

    public function getClient(): ClientEntityInterface
    {
        return $this->application;
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        return new CarbonImmutable($this->expires);
    }

    public function getUserIdentifier(): string
    {
        return $this->user_identifier;
    }

    public function getScopes(): array
    {
        $scopes = [];
        foreach ($this->scopes as $scope) {
            $scopes[] = new OAuthScope($scope);
        }

        return $scopes;
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $this->expires = (new CarbonImmutable($dateTime))->toDateTimeString();
    }

    public function setUserIdentifier($identifier): void
    {
        $this->user_identifier = (string) $identifier;
    }

    public function setClient(ClientEntityInterface $client): void
    {
        if ($client instanceof OAuthApplication) {
            $this->application = $client;
        }
    }

    public function addScope(ScopeEntityInterface $scope): void
    {
        $scopes = $this->scopes;
        $scopes[] = $scope->getIdentifier();
        $this->scopes = $scopes;
    }

    public function getRedirectUri(): string
    {
        return $this->redirect_uri;
    }

    public function setRedirectUri($uri): void
    {
        $this->redirect_uri = $uri;
    }
}
