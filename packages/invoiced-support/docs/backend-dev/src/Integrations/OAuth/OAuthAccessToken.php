<?php

namespace App\Integrations\OAuth;

use Carbon\CarbonImmutable;

class OAuthAccessToken
{
    public function __construct(
        public readonly string $accessToken,
        public readonly CarbonImmutable $accessTokenExpiration,
        public readonly string $refreshToken,
        public readonly ?CarbonImmutable $refreshTokenExpiration
    ) {
    }
}
