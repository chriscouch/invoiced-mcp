<?php

namespace App\Integrations\OAuth;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\Interfaces\OAuthIntegrationInterface;
use App\Integrations\OAuth\Traits\OAuthIntegrationTrait;
use Carbon\CarbonImmutable;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

abstract class AbstractOAuthIntegration implements OAuthIntegrationInterface
{
    use OAuthIntegrationTrait;

    protected stdClass $lastTokenResult;

    public function __construct(
        protected UrlGeneratorInterface $urlGenerator,
        protected HttpClientInterface $httpClient,
        protected array $settings
    ) {
    }

    public function getRedirectUrl(): string
    {
        return $this->urlGenerator->generate('oauth_finish', ['id' => $this->settings['serviceKey']], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function exchangeAuthCodeForToken(string $code): OAuthAccessToken
    {
        $token = $this->createToken('authorization_code', $code);

        return $this->makeAccessToken($token);
    }

    /**
     * Converts an access token result into an object. This
     * follows the conventional response format. May need to be
     * overridden by the integration if the format deviates.
     */
    protected function makeAccessToken(stdClass $token): OAuthAccessToken
    {
        $this->lastTokenResult = $token;

        $accessTokenExpiration = new CarbonImmutable('2099-01-01');
        if (isset($token->expires_in)) {
            $accessTokenExpiration = CarbonImmutable::now()->addSeconds($token->expires_in);
        }

        $refreshTokenExpiration = null;
        if (property_exists($token, 'refresh_token_expires_in')) {
            $refreshTokenExpiration = CarbonImmutable::now()->addSeconds($token->refresh_token_expires_in);
        }
        if (property_exists($token, 'x_refresh_token_expires_in')) {
            $refreshTokenExpiration = CarbonImmutable::now()->addSeconds($token->x_refresh_token_expires_in);
        }

        return new OAuthAccessToken(
            $token->access_token,
            $accessTokenExpiration,
            $token->refresh_token ?? '',
            $refreshTokenExpiration
        );
    }

    public function handleAccessToken(OAuthAccessToken $token, OAuthAccountInterface $account, ?Request $request = null): void
    {
        $account->setToken($token);
        $this->customAccountSetup($account, $request);
    }

    /**
     * @throws OAuthException
     */
    protected function customAccountSetup(OAuthAccountInterface $account, ?Request $request): void
    {
    }

    public function refresh(OAuthAccountInterface $account): void
    {
        $token = $this->createToken('refresh_token', $account->getToken()->refreshToken);
        $accessToken = $this->makeAccessToken($token);
        $this->handleAccessToken($accessToken, $account);
    }

    public function disconnect(OAuthAccountInterface $account): void
    {
        try {
            $this->httpClient->request(
                'POST',
                $this->getRevokeUrl(),
                $this->getHttpRequestOptions([
                    'token' => $account->getToken()->accessToken,
                ])
            );
        } catch (Throwable $e) {
            throw new OAuthException('Disconnect failed', 0, $e);
        }
    }

    /**
     * Used for testing.
     */
    public function setLastTokenResult(stdClass $lastTokenResult): void
    {
        $this->lastTokenResult = $lastTokenResult;
    }
}
