<?php

namespace App\Tests\Core\Auth\Storage;

use App\Core\Authentication\Models\ActiveSession;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Storage\SessionStorage;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionStorageTest extends AppTestCase
{
    private static User $ogUser;
    public static Mockery\MockInterface $mockSession;
    private static User $user;

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

    public function testGetAuthenticatedUserSessionInvalidUserAgent(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(self::$user->id());
        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Chrome');

        $request = $this->getRequest();

        $this->assertNull($storage->getAuthenticatedUser($request));
    }

    public function testGetAuthenticatedUserSessionDoesNotExist(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(12341234);
        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        $request = $this->getRequest();

        $this->assertNull($storage->getAuthenticatedUser($request));
    }

    public function testGetAuthenticatedUserSessionGuest(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(-1);
        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        $request = $this->getRequest();

        $user = $storage->getAuthenticatedUser($request);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(-1, $user->id());
        $this->assertFalse($user->isFullySignedIn());
    }

    public function testGetAuthenticatedUserGuest(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('get')
            ->andReturn(null);

        $request = $this->getRequest();

        $this->assertNull($storage->getAuthenticatedUser($request));
    }

    public function testSignIn(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('isStarted')
            ->andReturn(true);

        self::$mockSession->shouldReceive('getId')
            ->withArgs([])
            ->andReturn('sesh_1234')
            ->twice();

        self::$mockSession->shouldReceive('setId')
            ->withArgs(['sesh_1234'])
            ->once();

        self::$mockSession->shouldReceive('migrate')
            ->withArgs([true])
            ->once();

        self::$mockSession->shouldReceive('save')
            ->once();

        self::$mockSession->shouldReceive('start')
            ->once();

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(12341234);

        self::$mockSession->shouldReceive('replace')
            ->withArgs([[
                'user_id' => self::$user->id(),
                'user_agent' => 'Firefox',
            ]]);

        self::$mockSession->shouldReceive('has')
            ->withArgs(['oauth_authorization_request'])
            ->andReturn(false);
        self::$mockSession->shouldReceive('has')
            ->withArgs(['redirect_after_login'])
            ->andReturn(false);

        $request = $this->getRequest();

        $expectedExpires = time() + (int) ini_get('session.cookie_lifetime');

        $storage->signIn(self::$user, $request);

        // should record an active session
        $session = ActiveSession::where('id', 'sesh_1234')->oneOrNull();
        $this->assertInstanceOf(ActiveSession::class, $session);

        $expected = [
            'id' => 'sesh_1234',
            'ip' => '127.0.0.1',
            'user_agent' => 'Firefox',
            'expires' => $expectedExpires,
        ];
        $arr = $session->toArray();
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);
        $this->assertEquals(self::$user->id(), $session->user_id);
        $this->assertTrue($session->valid);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSignInAlreadySignedIn(): void
    {
        $storage = $this->getStorage();

        $user = new User(['id' => 12341234234]);
        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(12341234234);

        $request = $this->getRequest();

        // repeat calls should do nothing
        for ($i = 0; $i < 5; ++$i) {
            $storage->signIn($user, $request);
        }
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSignInTwoFactorRemembered(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('isStarted')
            ->andReturn(true);
        self::$mockSession->shouldReceive('getId')
            ->andReturn('sesh_12345');
        self::$mockSession->shouldReceive('migrate')
            ->withArgs([true]);
        self::$mockSession->shouldReceive('setId')
            ->withArgs(['sesh_12345']);
        self::$mockSession->shouldReceive('save');
        self::$mockSession->shouldReceive('start');
        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(null);
        self::$mockSession->shouldReceive('replace');
        self::$mockSession->shouldReceive('set')
            ->withArgs(['user_id', self::$user->id()]);
        self::$mockSession->shouldReceive('set')
            ->withArgs(['2fa_verified', true]);
        self::$mockSession->shouldReceive('has')
            ->withArgs(['oauth_authorization_request'])
            ->andReturn(true);
        self::$mockSession->shouldReceive('has')
            ->withArgs(['redirect_after_login'])
            ->andReturn(true);
        self::$mockSession->shouldReceive('get')
            ->withArgs(['oauth_authorization_request'])
            ->andReturn(1);
        self::$mockSession->shouldReceive('get')
            ->withArgs(['redirect_after_login'])
            ->andReturn(2);
        self::$mockSession->shouldReceive('set')
            ->withArgs(['oauth_authorization_request', 1]);
        self::$mockSession->shouldReceive('set')
            ->withArgs(['redirect_after_login', 2]);

        $request = $this->getRequest();

        $user = new User(['id' => self::$user->id()]);
        $user->markTwoFactorVerified();

        $storage->signIn($user, $request);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMarkTwoFactorVerified(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('set')
            ->withArgs(['2fa_verified', true]);

        $request = $this->getRequest();

        $storage->markTwoFactorVerified($request);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMarkRemembered(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('set')
            ->withArgs(['remembered', true]);

        $request = $this->getRequest();

        $storage->markRemembered($request);
    }

    /**
     * @depends testSignIn
     */
    public function testGetAuthenticatedUserSession(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(self::$user->id());

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        self::$mockSession->shouldReceive('get')
            ->withArgs(['2fa_verified'])
            ->andReturn(null);

        self::$mockSession->shouldReceive('getId')
            ->andReturn('sesh_1234');

        $request = $this->getRequest();

        // add a delay here so we can check if the updated_at timestamp
        // on the session has changed
        sleep(1);
        $expectedExpires = time() + (int) ini_get('session.cookie_lifetime');

        $user = $storage->getAuthenticatedUser($request);

        // should return a signed in user
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isFullySignedIn());

        // should record an active session
        $session = ActiveSession::where('id', 'sesh_1234')->oneOrNull();
        $this->assertInstanceOf('App\Core\Authentication\Models\ActiveSession', $session);

        $this->assertBetween(time() - $session->expires, 0, 3);

        $expected = [
            'id' => 'sesh_1234',
            'ip' => '127.0.0.1',
            'user_agent' => 'Firefox',
        ];
        $arr = $session->toArray();
        unset($arr['expires']);
        unset($arr['created_at']);
        unset($arr['updated_at']);
        $this->assertEquals($expected, $arr);
        $this->assertNotEquals($session->created_at, $session->updated_at);
        $this->assertEquals(self::$user->id(), $session->user_id);
        $this->assertTrue($session->valid);
    }

    /**
     * @depends testGetAuthenticatedUserSession
     */
    public function testGetAuthenticatedUserSessionInvalidated(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(self::$user->id());

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        self::$mockSession->shouldReceive('getId')
            ->andReturn('sesh_1234');

        $request = $this->getRequest();

        $session = new ActiveSession(['id' => 'sesh_1234']);
        $session->valid = false;
        $session->saveOrFail();

        $this->assertNull($storage->getAuthenticatedUser($request));
    }

    /**
     * @depends testSignIn
     */
    public function testGetAuthenticatedUserWith2FA(): void
    {
        $storage = $this->getStorage();

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_id'])
            ->andReturn(self::$user->id());

        self::$mockSession->shouldReceive('get')
            ->withArgs(['user_agent'])
            ->andReturn('Firefox');

        self::$mockSession->shouldReceive('get')
            ->withArgs(['2fa_verified'])
            ->andReturn(true);

        self::$mockSession->shouldReceive('getId')
            ->andReturn('sesh_12345');

        $request = $this->getRequest();

        $user = $storage->getAuthenticatedUser($request);

        // should return a signed in user with verified 2FA
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isFullySignedIn());
        $this->assertTrue($user->isTwoFactorVerified());
    }

    public function testSignOut(): void
    {
        $storage = $this->getStorage();

        $request = $this->getRequest();

        self::$mockSession->shouldReceive('isStarted')
            ->andReturn(true);

        self::$mockSession->shouldReceive('getId')
            ->andReturn('sesh_1234');

        self::$mockSession->shouldReceive('invalidate')
            ->once();

        $storage->signOut($request);

        $this->assertEquals(0, ActiveSession::where('id', 'sesh_1234')->count());
    }

    private function getStorage(): SessionStorage
    {
        return new SessionStorage(self::getService('test.database'));
    }

    private function getRequest(): Request
    {
        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox', 'REMOTE_ADDR' => '127.0.0.1']);
        $request->setSession(self::$mockSession);

        return $request;
    }
}
