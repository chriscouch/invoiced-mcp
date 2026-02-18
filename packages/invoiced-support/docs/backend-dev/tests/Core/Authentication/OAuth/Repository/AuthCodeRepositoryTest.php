<?php

namespace App\Tests\Core\Auth\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Authentication\OAuth\Models\OAuthAuthorizationCode;
use App\Core\Authentication\OAuth\Repository\AuthCodeRepository;
use App\Tests\AppTestCase;

class AuthCodeRepositoryTest extends AppTestCase
{
    private static OAuthApplication $application;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$application = OAuthApplication::makeNewApp('auth_code_repository');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$application->delete();
    }

    private function getRepository(): AuthCodeRepository
    {
        return new AuthCodeRepository();
    }

    public function testPersist(): void
    {
        $repository = $this->getRepository();

        // Generate and persist
        $identifier = uniqid();
        $authCode = $repository->getNewAuthCode();
        $authCode->setIdentifier($identifier);
        $authCode->setClient(self::$application);
        $repository->persistNewAuthCode($authCode);
        $this->assertInstanceOf(OAuthAuthorizationCode::class, OAuthAuthorizationCode::where('identifier', $identifier)->oneOrNull());

        // Test revocation
        $this->assertFalse($repository->isAuthCodeRevoked($identifier));
        $repository->revokeAuthCode($identifier);
        $this->assertTrue($repository->isAuthCodeRevoked($identifier));
    }
}
