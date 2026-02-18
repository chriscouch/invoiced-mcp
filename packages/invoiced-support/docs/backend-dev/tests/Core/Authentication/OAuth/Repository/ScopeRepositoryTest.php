<?php

namespace App\Tests\Core\Auth\OAuth\Repository;

use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Authentication\OAuth\Repository\ScopeRepository;
use App\Core\Authentication\OAuth\ValueObjects\OAuthScope;
use App\Tests\AppTestCase;

class ScopeRepositoryTest extends AppTestCase
{
    private const VALID_SCOPES = ['accounts_receivable', 'openid', 'read', 'read_write'];

    private static OAuthApplication $application;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$application = OAuthApplication::makeNewApp('scope_repository');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$application->delete();
    }

    public function getRepository(): ScopeRepository
    {
        return new ScopeRepository();
    }

    public function testRepository(): void
    {
        $repository = $this->getRepository();

        $scopes = [new OAuthScope('openid')];
        $this->assertEquals([], $repository->finalizeScopes($scopes, 'client_credentials', self::$application));
        $this->assertEquals($scopes, $repository->finalizeScopes($scopes, 'authorization_code', self::$application));
        $this->assertEquals([], $repository->finalizeScopes([], 'authorization_code', self::$application));

        $this->assertNull($repository->getScopeEntityByIdentifier('test'));
        foreach (self::VALID_SCOPES as $scope) {
            $this->assertInstanceOf(OAuthScope::class, $repository->getScopeEntityByIdentifier($scope));
        }
    }
}
