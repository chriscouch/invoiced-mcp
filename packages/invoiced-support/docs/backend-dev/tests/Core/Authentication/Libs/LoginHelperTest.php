<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Interfaces\StorageInterface;
use App\Core\Authentication\Interfaces\TwoFactorInterface;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Libs\RememberMeHelper;
use App\Core\Authentication\Libs\TwoFactorHelper;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\ActiveSession;
use App\Core\Authentication\Models\PersistentSession;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Storage\SessionStorage;
use App\Core\Statsd\StatsdClient;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LoginHelperTest extends AppTestCase
{
    private static User $user;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::getService('test.database')->delete('Users', ['email' => 'test+auth@example.com']);

        self::$user = self::getService('test.user_registration')->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test+auth@example.com',
            'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
            'ip' => '127.0.0.1',
        ], false, false);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$user->delete();
    }

    public function testGetCookieBag(): void
    {
        $this->assertInstanceOf(ParameterBag::class, $this->getLoginHelper()->getCookieBag());
    }

    public function testGetCurrentUser(): void
    {
        $userContext = $this->getUserContext();
        $user = new User(['id' => 1234]);
        $userContext->set($user);
        $this->assertEquals($user, $userContext->get());
        $this->assertEquals($user, $userContext->get());
    }

    public function testHasCurrentUser(): void
    {
        $userContext = $this->getUserContext();
        $this->assertFalse($userContext->has());
        $userContext->set(new User());
        $this->assertTrue($userContext->has());
    }

    public function testGetAuthenticatedUser(): void
    {
        $request = $this->getRequest();

        $user = new User(['id' => 1234]);

        $storage = Mockery::mock(StorageInterface::class);
        $loginHelper = $this->getLoginHelper($storage);
        $storage->shouldReceive('getAuthenticatedUser')
            ->withArgs([$request])
            ->andReturn($user)
            ->once();

        $this->assertEquals($user, $loginHelper->getAuthenticatedUser($request));
    }

    public function testGetAuthenticatedUserFail(): void
    {
        self::$user->setIsFullySignedIn(false);
        $request = $this->getRequest();

        $storage = Mockery::mock(StorageInterface::class);
        $loginHelper = $this->getLoginHelper($storage);
        $storage->shouldReceive('getAuthenticatedUser')
            ->withArgs([$request])
            ->andReturn(null)
            ->once();

        $user = $loginHelper->getAuthenticatedUser($request);

        $this->assertNull($user);
    }

    public function testGetAuthenticatedUserNeedsTwoFactor(): void
    {
        $request = $this->getRequest();

        $user = new User(['id' => 1234, 'authy_id' => 1, 'verified_2fa' => true]);
        $user->setIsFullySignedIn();

        $storage = Mockery::mock(StorageInterface::class);
        $loginHelper = $this->getLoginHelper($storage);
        $storage->shouldReceive('getAuthenticatedUser')
            ->withArgs([$request])
            ->andReturn($user)
            ->once();

        $this->assertEquals($user, $loginHelper->getAuthenticatedUser($request));
        $this->assertFalse($user->isFullySignedIn());
        $this->assertFalse($user->isTwoFactorVerified());
    }

    public function testGetAuthenticatedUserTwoFactorVerified(): void
    {
        $request = $this->getRequest();

        $user = new User(['id' => 1234, 'authy_id' => 1, 'verified_2fa' => true]);
        $user->setIsFullySignedIn()->markTwoFactorVerified();

        $storage = Mockery::mock(StorageInterface::class);
        $loginHelper = $this->getLoginHelper($storage);
        $storage->shouldReceive('getAuthenticatedUser')
            ->withArgs([$request])
            ->andReturn($user)
            ->once();

        $this->assertEquals($user, $loginHelper->getAuthenticatedUser($request));
        $this->assertTrue($user->isFullySignedIn());
        $this->assertTrue($user->isTwoFactorVerified());
    }

    public function testSignInUser(): void
    {
        self::$user->setIsFullySignedIn(false);

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('signIn')
            ->andReturn(true)
            ->once();
        $userContext = $this->getUserContext();
        $loginHelper = $this->getLoginHelper($storage, $userContext);
        $request = $this->getRequest();

        $user = $loginHelper->signInUser($request, self::$user, 'web');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isFullySignedIn());

        $this->assertEquals($user, $userContext->get());

        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.login')
            ->where('auth_strategy', 'web')
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testSignInUserNeedsTwoFactor(): void
    {
        self::$user->setIsFullySignedIn();
        self::$user->authy_id = '1234';
        self::$user->verified_2fa = true;

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('signIn')
            ->andReturn(true)
            ->once();

        $userContext = $this->getUserContext();
        $loginHelper = $this->getLoginHelper($storage, $userContext);

        $request = $this->getRequest();

        $user = $loginHelper->signInUser($request, self::$user, 'web');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertFalse($user->isFullySignedIn());
        $this->assertFalse($user->isTwoFactorVerified());

        $this->assertEquals($user, $userContext->get());
    }

    public function testSignInUserTwoFactorVerified(): void
    {
        self::$user->setIsFullySignedIn(false);
        self::$user->markTwoFactorVerified();

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('signIn')
            ->andReturn(true)
            ->once();
        $userContext = $this->getUserContext();
        $loginHelper = $this->getLoginHelper($storage, $userContext);
        $request = $this->getRequest();

        $user = $loginHelper->signInUser($request, self::$user, '2fa_test');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isFullySignedIn());
        $this->assertTrue($user->isTwoFactorVerified());

        $this->assertEquals($user, $userContext->get());

        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.login')
            ->where('auth_strategy', '2fa_test')
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testSignInUserRemember(): void
    {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('signIn')
            ->andReturn(true)
            ->once();
        $userContext = $this->getUserContext();
        $loginHelper = $this->getLoginHelper($storage, $userContext);
        $request = $this->getRequest();
        $rememberMeHelper = $this->getRememberMeHelper();

        $user = $loginHelper->signInUser($request, self::$user, 'test_strat');
        $rememberMeHelper->rememberUser($request, $user);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isFullySignedIn());

        $this->assertEquals($user, $userContext->get());

        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.login')
            ->where('auth_strategy', 'test_strat')
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testLogout(): void
    {
        $request = $this->getRequest();

        $storage = Mockery::mock(StorageInterface::class);
        $userContext = $this->getUserContext();
        $storage->shouldReceive('signOut')
            ->withArgs([$request])
            ->andReturn(true)
            ->once();
        $storage->shouldReceive('signIn')
            ->andReturn(true);
        $loginHelper = $this->getLoginHelper($storage, $userContext);

        $loginHelper->logout($request);

        $currentUser = $userContext->get();
        $this->assertNull($currentUser);
    }

    public function testSignOutAllSessions(): void
    {
        // create sessions
        $session = new ActiveSession();
        $session->id = 'sesh_1234';
        $session->user_id = (int) self::$user->id();
        $session->ip = '127.0.0.1';
        $session->user_agent = 'Firefox';
        $session->expires = strtotime('+1 month');
        $this->assertTrue($session->save());

        $persistent = new PersistentSession();
        $persistent->email = 'test+auth@example.com';
        $persistent->user_id = (int) self::$user->id();
        $persistent->series = str_repeat('a', 128);
        $persistent->token = str_repeat('a', 128);
        $this->assertTrue($persistent->save());

        $this->getLoginHelper()->signOutAllSessions(self::$user);

        $n = ActiveSession::where('id', 'sesh_1234')
            ->where('valid', false)
            ->count();
        $this->assertEquals(1, $n);

        $this->assertEquals(0, PersistentSession::where('user_id', self::$user->id())->count());
    }

    private function getRequest(): Request
    {
        return new Request([], [], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']);
    }

    private function getRequestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push($this->getRequest());

        return $requestStack;
    }

    private function getUserContext(?LoginHelper $loginHelper = null): UserContext
    {
        $requestStack = $this->getRequestStack();
        $loginHelper ??= $this->getLoginHelper();

        return new UserContext($requestStack, $loginHelper);
    }

    private function getLoginHelper(?StorageInterface $storage = null, ?UserContext $userContext = null): LoginHelper
    {
        $storage ??= new SessionStorage(self::getService('test.database'));
        $requestStack = $this->getRequestStack();
        $loginHelper = Mockery::mock(LoginHelper::class);
        $userContext ??= new UserContext($requestStack, $loginHelper);
        $rememberMe = $this->getRememberMeHelper();
        $twoFactor = Mockery::mock(TwoFactorInterface::class);
        $twoFactorHelper = new TwoFactorHelper($twoFactor, $storage, $loginHelper, $rememberMe);
        $twoFactorHelper->setStatsd(new StatsdClient());

        $loginHelper = new LoginHelper(self::getService('test.database'), $storage, $userContext, self::getService('event_dispatcher'), $rememberMe, $twoFactorHelper);
        $loginHelper->setStatsd(new StatsdClient());

        return $loginHelper;
    }

    private function getRememberMeHelper(): RememberMeHelper
    {
        $rememberMe = Mockery::mock(RememberMeHelper::class);
        $rememberMe->shouldReceive('rememberUser');
        $rememberMe->shouldReceive('destroyRememberMeCookie');
        $rememberMe->shouldReceive('getUserRememberMe')->andReturn(null);

        return $rememberMe;
    }
}
