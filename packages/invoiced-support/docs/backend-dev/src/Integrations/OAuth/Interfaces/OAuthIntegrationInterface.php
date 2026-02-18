<?php

namespace App\Integrations\OAuth\Interfaces;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthAccessToken;
use Symfony\Component\HttpFoundation\Request;

interface OAuthIntegrationInterface
{
    public function getAuthorizationUrl(string $state): string;

    public function getRedirectUrl(): string;

    /**
     * @throws OAuthException
     */
    public function exchangeAuthCodeForToken(string $code): OAuthAccessToken;

    /**
     * @throws OAuthException
     */
    public function handleAccessToken(OAuthAccessToken $token, OAuthAccountInterface $account, ?Request $request = null): void;

    /**
     * @throws OAuthException
     */
    public function refresh(OAuthAccountInterface $account): void;

    /**
     * @throws OAuthException
     */
    public function disconnect(OAuthAccountInterface $account): void;

    public function getAccount(): ?OAuthAccountInterface;

    public function makeAccount(): OAuthAccountInterface;

    public function getSuccessRedirectUrl(): string;
}
