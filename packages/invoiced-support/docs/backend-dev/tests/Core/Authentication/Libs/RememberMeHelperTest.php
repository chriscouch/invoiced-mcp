<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Interfaces\StorageInterface;
use App\Core\Authentication\Libs\RememberMeHelper;
use App\Core\Authentication\Models\User;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class RememberMeHelperTest extends AppTestCase
{
    private static User $ogUser;
    public static Mockery\MockInterface $mockSession;
    private static User $user;
    private static string $rememberCookie;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$ogUser = self::getService('test.user_context')->get();

        self::getService('test.database')->delete('Users', ['email' => 'test+auth@example.com']);
        self::getService('test.database')->delete('ActiveSessions', ['id' => 'sesh_1234']);

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

    protected function setUp(): void
    {
        parent::setUp();
        self::$mockSession = Mockery::mock(SessionInterface::class);
        self::$mockSession->shouldReceive('getName')
            ->andReturn('mysession');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::getService('test.user_context')->set(self::$ogUser);
    }

    public function testRememberUser(): void
    {
        $helper = $this->getHelper();
        $request = $this->getRequest();

        $helper->rememberUser($request, self::$user);

        $cookie = $helper->getLoginHelper()->getCookieBag()->get('mysession-remember');
        $this->assertInstanceOf(Cookie::class, $cookie);
        self::$rememberCookie = (string) $cookie->getValue();
    }

    /**
     * @depends testRememberUser
     */
    public function testGetUserRememberMe(): void
    {
        $helper = $this->getHelper();

        $request = $this->getRequest();
        $request->cookies->set('mysession-remember', self::$rememberCookie);

        $user = $helper->getUserRememberMe($request);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isFullySignedIn());
        $this->assertFalse($user->isTwoFactorVerified());
    }

    public function testRememberUser2FAVerified(): void
    {
        $helper = $this->getHelper();
        $request = $this->getRequest();

        self::$user->markTwoFactorVerified();

        $helper->rememberUser($request, self::$user);

        $cookie = $helper->getLoginHelper()->getCookieBag()->get('mysession-remember');
        $this->assertInstanceOf(Cookie::class, $cookie);
        self::$rememberCookie = (string) $cookie->getValue();
    }

    /**
     * @depends testRememberUser2FAVerified
     */
    public function testGetUserRememberMe2FA(): void
    {
        $request = $this->getRequest();
        $request->cookies->set('mysession-remember', self::$rememberCookie);

        $helper = $this->getHelper();

        $user = $helper->getUserRememberMe($request);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isFullySignedIn());
        $this->assertTrue($user->isTwoFactorVerified());

        // NOTE: We are using the in-memory storage in this test, however,
        // if this were using the session storage it would also mark
        // the session as two factor verified whenever the user is
        // signed in from the remember me cookie.
    }

    private function getHelper(): RememberMeHelper
    {
        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('markRemembered');
        $loginHelper = self::getService('test.login_helper');

        return new RememberMeHelper(self::getService('test.database'), $storage, $loginHelper);
    }

    private function getRequest(): Request
    {
        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox', 'REMOTE_ADDR' => '127.0.0.1']);
        $request->setSession(self::$mockSession);

        return $request;
    }
}
