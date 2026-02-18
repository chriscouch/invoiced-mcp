<?php

namespace App\Core\Authentication\OAuth;

use App\Core\Authentication\OAuth\Repository\AuthCodeRepository;
use App\Core\Authentication\OAuth\Repository\RefreshTokenRepository;
use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OAuthServerFactory
{
    private const AUTH_CODE_EXPIRY = 'PT10M'; // 10 minutes
    private const ACCESS_TOKEN_EXPIRY = 'PT1H'; // 1 hour
    private const REFRESH_TOKEN_EXPIRY = 'P1Y'; // 1 year

    public function __construct(
        private AuthorizationServer $authorizationServer,
        private ResourceServer $resourceServer,
        private AuthCodeRepository $authCodeRepository,
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    public function getAuthorizationServer(): AuthorizationServer
    {
        // Enable the authentication code grant on the server
        $grant = new AuthCodeGrant(
            $this->authCodeRepository,
            $this->refreshTokenRepository,
            new DateInterval(self::AUTH_CODE_EXPIRY)
        );

        $grant->setRefreshTokenTTL(new DateInterval(self::REFRESH_TOKEN_EXPIRY));

        $this->authorizationServer->enableGrantType(
            $grant,
            new DateInterval(self::ACCESS_TOKEN_EXPIRY)
        );

        // Enable the refresh token grant on the server
        $grant = new RefreshTokenGrant($this->refreshTokenRepository);
        $grant->setRefreshTokenTTL(new DateInterval(self::REFRESH_TOKEN_EXPIRY));

        $this->authorizationServer->enableGrantType(
            $grant,
            new DateInterval(self::ACCESS_TOKEN_EXPIRY)
        );

        return $this->authorizationServer;
    }

    public function getResourceServer(): ResourceServer
    {
        return $this->resourceServer;
    }

    public function convertRequestToPsr(Request $request): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        return $psrHttpFactory->createRequest($request);
    }

    public function convertResponseToPsr(Response $response): ResponseInterface
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        return $psrHttpFactory->createResponse($response);
    }
}
