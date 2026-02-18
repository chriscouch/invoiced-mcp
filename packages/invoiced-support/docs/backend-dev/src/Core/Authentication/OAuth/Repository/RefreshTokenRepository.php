<?php

namespace App\Core\Authentication\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthRefreshToken;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new OAuthRefreshToken();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        if ($refreshTokenEntity instanceof OAuthRefreshToken) {
            $refreshTokenEntity->saveOrFail();
        }
    }

    public function revokeRefreshToken($tokenId): void
    {
        OAuthRefreshToken::where('identifier', $tokenId)->delete();
    }

    public function isRefreshTokenRevoked($tokenId): bool
    {
        return 0 == OAuthRefreshToken::where('identifier', $tokenId)->count();
    }
}
