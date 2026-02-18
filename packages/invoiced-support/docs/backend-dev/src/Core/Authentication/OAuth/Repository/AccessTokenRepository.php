<?php

namespace App\Core\Authentication\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthAccessToken;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null): AccessTokenEntityInterface
    {
        $accessToken = new OAuthAccessToken();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        if ($userIdentifier) {
            $accessToken->setUserIdentifier($userIdentifier);
        }

        return $accessToken;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        if ($accessTokenEntity instanceof OAuthAccessToken) {
            $accessTokenEntity->saveOrFail();
        }
    }

    public function revokeAccessToken($tokenId): void
    {
        OAuthAccessToken::where('identifier', $tokenId)->delete();
    }

    public function isAccessTokenRevoked($tokenId): bool
    {
        return 0 == OAuthAccessToken::where('identifier', $tokenId)->count();
    }
}
