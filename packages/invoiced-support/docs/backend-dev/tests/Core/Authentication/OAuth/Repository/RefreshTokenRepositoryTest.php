<?php

namespace App\Tests\Core\Auth\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthAccessToken;
use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Authentication\OAuth\Models\OAuthRefreshToken;
use App\Core\Authentication\OAuth\Repository\RefreshTokenRepository;
use App\Tests\AppTestCase;

class RefreshTokenRepositoryTest extends AppTestCase
{
    private static OAuthApplication $application;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$application = OAuthApplication::makeNewApp('refresh_token_repository');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::getService('test.database')->executeStatement('DELETE FROM OAuthAccessTokens WHERE identifier="test"');
        self::$application->delete();
    }

    private function getRepository(): RefreshTokenRepository
    {
        return new RefreshTokenRepository();
    }

    private function getAccessToken(): OAuthAccessToken
    {
        $accessToken = new OAuthAccessToken();
        $accessToken->application = self::$application;
        $accessToken->identifier = 'test';
        $accessToken->saveOrFail();

        return $accessToken;
    }

    public function testPersist(): void
    {
        $repository = $this->getRepository();

        // Generate and persist
        $identifier = uniqid();
        /** @var OAuthRefreshToken $refreshToken */
        $refreshToken = $repository->getNewRefreshToken();
        $this->assertInstanceOf(OAuthRefreshToken::class, $refreshToken);
        $refreshToken->setIdentifier($identifier);
        $refreshToken->setAccessToken($this->getAccessToken());
        $repository->persistNewRefreshToken($refreshToken);
        $this->assertInstanceOf(OAuthRefreshToken::class, OAuthRefreshToken::where('identifier', $identifier)->oneOrNull());

        // Test revocation
        $this->assertFalse($repository->isRefreshTokenRevoked($identifier));
        $repository->revokeRefreshToken($identifier);
        $this->assertTrue($repository->isRefreshTokenRevoked($identifier));
    }
}
