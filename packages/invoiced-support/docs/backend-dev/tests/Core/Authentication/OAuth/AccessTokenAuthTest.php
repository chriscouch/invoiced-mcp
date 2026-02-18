<?php

namespace App\Tests\Core\Auth\OAuth;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\OAuth\AccessTokenAuth;
use App\Core\Authentication\OAuth\OAuthServerFactory;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;
use App\Tests\AppTestCase;
use League\OAuth2\Server\ResourceServer;
use Mockery;
use Nyholm\Psr7\ServerRequest;
use Symfony\Component\HttpFoundation\Request;

class AccessTokenAuthTest extends AppTestCase
{
    private static User $ogUser;
    private static Model $requester;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$ogUser = self::getService('test.user_context')->get();
        self::$requester = ACLModelRequester::get();
    }

    public function assertPostConditions(): void
    {
        parent::assertPostConditions();
        self::getService('test.user_context')->set(self::$ogUser);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ACLModelRequester::set(self::$requester);
    }

    private function getAuth(): AccessTokenAuth
    {
        return self::getService('test.access_token_auth');
    }

    public function testIsOAuthRequest(): void
    {
        $auth = $this->getAuth();
        $request = new Request();
        $this->assertFalse($auth->isOAuthRequest($request));

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer test']);
        $this->assertTrue($auth->isOAuthRequest($request));
    }

    public function testHandleRequestUser(): void
    {
        $user = self::getService('test.user_context')->get();

        $authenticatedRequest = (new ServerRequest('GET', '/'))
            ->withAttribute('oauth_user_id', 'user:'.$user->id());

        $resourceServer = Mockery::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($authenticatedRequest);
        $factory = Mockery::mock(OAuthServerFactory::class)->makePartial();
        $factory->shouldReceive('getResourceServer')->andReturn($resourceServer);

        $auth = $this->getAuth();
        $auth->setFactory($factory);

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer test', 'HTTP_HOST' => 'localhost']);
        $auth->handleRequest($request);

        $this->assertEquals($user->id(), self::getService('test.user_context')->get()->id());
        $requester = ACLModelRequester::get();
        $this->assertInstanceOf(User::class, $requester);
        $this->assertEquals($user->id(), $requester->id());
    }

    public function testHandleRequestTenant(): void
    {
        $authenticatedRequest = (new ServerRequest('GET', '/'))
            ->withAttribute('oauth_user_id', 'tenant:'.self::$company->id());

        $resourceServer = Mockery::mock(ResourceServer::class);
        $resourceServer->shouldReceive('validateAuthenticatedRequest')->andReturn($authenticatedRequest);
        $factory = Mockery::mock(OAuthServerFactory::class)->makePartial();
        $factory->shouldReceive('getResourceServer')->andReturn($resourceServer);

        $auth = $this->getAuth();
        $auth->setFactory($factory);

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer test', 'HTTP_HOST' => 'localhost']);
        $auth->handleRequest($request);

        $this->assertTrue($request->attributes->get('skip_api_authentication'));

        $this->assertEquals(-3, self::getService('test.user_context')->get()->id());
        $requester = ACLModelRequester::get();
        $this->assertInstanceOf(Company::class, $requester);
        $this->assertEquals(self::$company->id(), $requester->id());
        $this->assertEquals(self::$company->id(), self::getService('test.tenant')->get()->id());
    }
}
