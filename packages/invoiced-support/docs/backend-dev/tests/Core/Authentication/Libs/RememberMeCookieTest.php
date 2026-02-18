<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Libs\RememberMeCookie;
use App\Core\Authentication\Models\User;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class RememberMeCookieTest extends AppTestCase
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

    public function testEncode(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', '1234', '_token');

        $expected = 'eyJ1c2VyX2VtYWlsIjoidGVzdCthdXRoQGV4YW1wbGUuY29tIiwiYWdlbnQiOiJGaXJlZm94Iiwic2VyaWVzIjoiMTIzNCIsInRva2VuIjoiX3Rva2VuIn0=';
        $this->assertEquals($expected, $cookie->encode());
    }

    public function testDecode(): void
    {
        $encoded = 'eyJ1c2VyX2VtYWlsIjoidGVzdEBleGFtcGxlLmNvbSIsInNlcmllcyI6IjEyMzQiLCJ0b2tlbiI6Il90b2tlbiIsImFnZW50IjoiRmlyZWZveCJ9';
        $cookie = RememberMeCookie::decode($encoded);

        $this->assertInstanceOf('App\Core\Authentication\Libs\RememberMeCookie', $cookie);

        $this->assertEquals('test@example.com', $cookie->getEmail());
        $this->assertEquals('Firefox', $cookie->getUserAgent());
        $this->assertEquals('1234', $cookie->getSeries());
        $this->assertEquals('_token', $cookie->getToken());
        $this->assertTrue($cookie->isValid());
    }

    public function testDecodeFail(): void
    {
        $cookie = RememberMeCookie::decode('');
        $this->assertInstanceOf('App\Core\Authentication\Libs\RememberMeCookie', $cookie);

        $this->assertEquals('', $cookie->getEmail());
        $this->assertEquals('', $cookie->getUserAgent());
        $this->assertEquals('', $cookie->getToken());
        $this->assertEquals('', $cookie->getSeries());
        $this->assertFalse($cookie->isValid());

        $encoded = 'WHAT IS THIS NONSENSE';
        $cookie = RememberMeCookie::decode($encoded);

        $this->assertInstanceOf('App\Core\Authentication\Libs\RememberMeCookie', $cookie);

        $this->assertEquals('', $cookie->getEmail());
        $this->assertEquals('', $cookie->getUserAgent());
        $this->assertEquals('', $cookie->getToken());
        $this->assertEquals('', $cookie->getSeries());
        $this->assertFalse($cookie->isValid());
    }

    public function testGenerateTokens(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $this->assertEquals(32, strlen($cookie->getSeries()));
        $this->assertEquals(32, strlen($cookie->getToken()));
        $this->assertNotEquals($cookie->getSeries(), $cookie->getToken());

        $cookie2 = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $this->assertNotEquals($cookie->getSeries(), $cookie2->getSeries())
        ;
        $this->assertNotEquals($cookie->getToken(), $cookie2->getToken());
    }

    public function testGetExpires(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $this->assertEquals(7776000, $cookie->getExpires());
    }

    public function testIsValid(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', '1234', '_token');
        $this->assertTrue($cookie->isValid());

        $cookie = new RememberMeCookie('asdfasdf', 'Firefox', '1234', '_token');
        $this->assertFalse($cookie->isValid());

        $cookie = new RememberMeCookie('test+auth@example.com', '', '1234', '_token');
        $this->assertFalse($cookie->isValid());

        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', '', '_token');
        $this->assertFalse($cookie->isValid());

        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', '1234', '');
        $this->assertFalse($cookie->isValid());
    }

    public function testPersist(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $session = $cookie->persist(self::$user);
        $this->assertInstanceOf('App\Core\Authentication\Models\PersistentSession', $session);
        $this->assertTrue($session->persisted());
        $this->assertFalse($session->two_factor_verified);
    }

    public function testPersistFail(): void
    {
        $this->expectException('Exception');

        $user = new User(['id' => 123412341234]);

        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $cookie->persist($user);
    }

    public function testVerifyNotValid(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', '1234', '');

        $request = Mockery::mock(Request::class);

        $this->assertNull($cookie->verify($request, self::getService('test.database')));
    }

    public function testVerifyUserAgentFail(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', '1234', '_token');

        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Chrome']);

        $this->assertNull($cookie->verify($request, self::getService('test.database')));
    }

    public function testVerifyUserNotFound(): void
    {
        $cookie = new RememberMeCookie('test2@example.com', 'Firefox', '1234', '_token');

        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);

        $this->assertNull($cookie->verify($request, self::getService('test.database')));
    }

    public function testVerifySeriesNotFound(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $cookie->persist(self::$user);

        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', '12345', $cookie->getToken());

        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);

        $this->assertNull($cookie->verify($request, self::getService('test.database')));
    }

    public function testVerifyTokenMismatch(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $cookie->persist(self::$user);

        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', $cookie->getSeries(), '_token2');

        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);

        $this->assertNull($cookie->verify($request, self::getService('test.database')));
    }

    public function testVerify(): void
    {
        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $cookie->persist(self::$user);

        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', $cookie->getSeries(), $cookie->getToken());

        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);

        $user = $cookie->verify($request, self::getService('test.database'));

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertFalse($user->isTwoFactorVerified());
    }

    public function testVerifyWithTwoFactor(): void
    {
        self::$user->markTwoFactorVerified();

        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox');
        $cookie->persist(self::$user);

        $cookie = new RememberMeCookie('test+auth@example.com', 'Firefox', $cookie->getSeries(), $cookie->getToken());

        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);

        $user = $cookie->verify($request, self::getService('test.database'));

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue($user->isTwoFactorVerified());
    }
}
