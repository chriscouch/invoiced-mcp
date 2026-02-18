<?php

namespace App\Core\Authentication\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthAuthorizationCode;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new OAuthAuthorizationCode();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        if ($authCodeEntity instanceof OAuthAuthorizationCode) {
            $authCodeEntity->saveOrFail();
        }
    }

    public function revokeAuthCode($codeId): void
    {
        OAuthAuthorizationCode::where('identifier', $codeId)->delete();
    }

    public function isAuthCodeRevoked($codeId): bool
    {
        return 0 == OAuthAuthorizationCode::where('identifier', $codeId)->count();
    }
}
