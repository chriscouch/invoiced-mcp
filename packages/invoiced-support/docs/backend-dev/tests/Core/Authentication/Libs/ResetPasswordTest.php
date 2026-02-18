<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\ResetPassword;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Models\UserLink;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class ResetPasswordTest extends AppTestCase
{
    private static User $user;
    private static User $ogUser;

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

        self::$ogUser = self::getService('test.user_context')->get();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$user->delete();
    }

    public function assertPostConditions(): void
    {
        parent::assertPostConditions();
        self::getService('test.user_context')->set(self::$ogUser);
    }

    public function testBuildLink(): void
    {
        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();

        $link = $reset->buildLink((int) self::$user->id(), $ip, 'Firefox');
        $this->assertInstanceOf('App\Core\Authentication\Models\UserLink', $link);
        $this->assertTrue($link->persisted());

        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.request_password_reset')
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testBuildLinkFail(): void
    {
        $this->expectException('Exception');
        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();
        $reset->buildLink(123412341234, $ip, 'Firefox');
    }

    public function testGetUserFromTokenInvalid(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('This link has expired or is invalid.');

        $reset = $this->getResetPassword();
        $reset->getUserFromToken('blah');
    }

    public function testGetUserFromToken(): void
    {
        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();

        $link = $reset->buildLink((int) self::$user->id(), $ip, 'Firefox');

        $user = $reset->getUserFromToken($link->link);
        $this->assertInstanceOf('App\Core\Authentication\Models\User', $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    /**
     * @depends testGetUserFromToken
     */
    public function testGetUserFromTokenExpired(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('This link has expired or is invalid.');

        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();

        $link = $reset->buildLink((int) self::$user->id(), $ip, 'Firefox');
        $link->created_at = strtotime('-10 years');
        $this->assertTrue($link->save());

        $reset->getUserFromToken($link->link);
    }

    public function testStep1ValidationFailed(): void
    {
        self::getService('test.database')->delete('UserLinks', ['type' => UserLink::FORGOT_PASSWORD, 'user_id' => self::$user->id()]);

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Please enter a valid email address.');

        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();
        $reset->step1('invalidemail', $ip, 'Firefox');
    }

    public function testStep1NoEmailMatch(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('We could not find a match for that email address.');

        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();
        $reset->step1('nomatch@example.com', $ip, 'Firefox');
    }

    public function testStep1(): void
    {
        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();
        $reset->step1('test+auth@example.com', $ip, 'Firefox');
        $n = UserLink::where('type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user)
            ->count();
        $this->assertEquals(1, $n);

        // repeat calls should do nothing
        for ($i = 0; $i < 5; ++$i) {
            $reset->step1('test+auth@example.com', $ip, 'Firefox');
        }

        $n = UserLink::where('type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user)
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testStep2Invalid(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('This link has expired or is invalid.');

        $reset = $this->getResetPassword();
        $request = $this->getRequest();

        $reset->step2('blah', ['password', 'password'], $request);
    }

    public function testStep2BadPassword(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('The supplied password did not meet the password policy.');

        self::getService('test.database')->delete('UserLinks', ['type' => UserLink::FORGOT_PASSWORD, 'user_id' => self::$user->id()]);

        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();

        $link = $reset->buildLink((int) self::$user->id(), $ip, 'Firefox');
        $request = $this->getRequest();

        $reset->step2($link->link, ['f', 'f'], $request);
    }

    public function testStep2(): void
    {
        self::getService('test.database')->delete('UserLinks', ['type' => UserLink::FORGOT_PASSWORD, 'user_id' => self::$user->id()]);

        $reset = $this->getResetPassword();
        $ip = $this->getIpAddress();

        $link = $reset->buildLink((int) self::$user->id(), $ip, 'Firefox');
        $request = $this->getRequest();

        $oldUserPassword = self::$user->password;
        $reset->step2($link->link, ['TestPassw0rd!2', 'TestPassw0rd!2'], $request);

        self::$user->refresh();
        $this->assertNotEquals($oldUserPassword, self::$user->password);
        $n = UserLink::where('type', UserLink::FORGOT_PASSWORD)
            ->where('user_id', self::$user)
            ->count();
        $this->assertEquals(0, $n);
    }

    private function getResetPassword(): ResetPassword
    {
        return self::getService('test.reset_password');
    }

    private function getRequest(): Request
    {
        return new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
    }

    private function getIpAddress(): string
    {
        return random_int(1, 255).'.'.random_int(1, 255).'.'.random_int(1, 255).'.'.random_int(1, 255);
    }
}
