<?php

namespace App\Core\Authentication\OAuth\Repository;

use App\Core\Authentication\OAuth\ValueObjects\OAuthScope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

class ScopeRepository implements ScopeRepositoryInterface
{
    private const VALID_SCOPES = ['accounts_receivable', 'openid', 'read', 'read_write'];

    public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface
    {
        if (!in_array($identifier, self::VALID_SCOPES)) {
            return null;
        }

        return new OAuthScope($identifier);
    }

    public function finalizeScopes(array $scopes, $grantType, ClientEntityInterface $clientEntity, $userIdentifier = null): array
    {
        if (!in_array($grantType, ['authorization_code', 'refresh_token'])) {
            return [];
        }

        return $scopes;
    }
}
