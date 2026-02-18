<?php

namespace App\Tests\Core\Auth\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Authentication\OAuth\Repository\ClientRepository;
use App\Tests\AppTestCase;

class ClientRepositoryTest extends AppTestCase
{
    private static OAuthApplication $application;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$application = OAuthApplication::makeNewApp('client_repository');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$application->delete();
    }

    private function getRepository(): ClientRepository
    {
        return new ClientRepository();
    }

    public function testRepository(): void
    {
        $repository = $this->getRepository();

        $this->assertNull($repository->getClientEntity('doesnotexist'));
        $this->assertInstanceOf(OAuthApplication::class, $repository->getClientEntity(self::$application->identifier));

        $this->assertTrue($repository->validateClient(self::$application->identifier, self::$application->secret, 'authorization_code'));
        $this->assertFalse($repository->validateClient(self::$application->identifier, 'invalid secret', 'authorization_code'));
        $this->assertFalse($repository->validateClient(self::$application->identifier, self::$application->secret, 'client_credentials'));
        $this->assertFalse($repository->validateClient('doesnotexist', self::$application->secret, 'authorization_code'));
    }
}
