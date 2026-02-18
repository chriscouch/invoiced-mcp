<?php

namespace App\Tests\Core\Auth\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthAccessToken;
use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Authentication\OAuth\Repository\AccessTokenRepository;
use App\Tests\AppTestCase;

class AccessTokenRepositoryTest extends AppTestCase
{
    private static OAuthApplication $application;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$application = OAuthApplication::makeNewApp('access_token_repository');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$application->delete();
    }

    private function getRepository(): AccessTokenRepository
    {
        return new AccessTokenRepository();
    }

    public function testPersist(): void
    {
        $repository = $this->getRepository();

        // Generate and persist
        $identifier = uniqid();
        $accessToken = $repository->getNewToken(self::$application, [], 'test');
        $accessToken->setIdentifier($identifier);
        $repository->persistNewAccessToken($accessToken);
        $this->assertInstanceOf(OAuthAccessToken::class, OAuthAccessToken::where('identifier', $identifier)->oneOrNull());

        // Test revocation
        $this->assertFalse($repository->isAccessTokenRevoked($identifier));
        $repository->revokeAccessToken($identifier);
        $this->assertTrue($repository->isAccessTokenRevoked($identifier));
    }
}
