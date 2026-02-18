<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Libs\VerifyEmailHelper;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Models\UserLink;
use App\Tests\AppTestCase;

class VerifyEmailHelperTest extends AppTestCase
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

    public function testSendVerificationEmail(): void
    {
        $verify = $this->getVerifyEmail();
        $verify->sendVerificationEmail(self::$user);
        $this->assertFalse(self::$user->isVerified(false));
    }

    public function testVerifyEmailWithTokenInvalid(): void
    {
        $verify = $this->getVerifyEmail();
        $this->assertNull($verify->verifyEmailWithToken('blah', true));
    }

    public function testVerifyEmailWithToken(): void
    {
        $link = new UserLink();
        $link->user_id = (int) self::$user->id();
        $link->type = UserLink::VERIFY_EMAIL;
        $this->assertTrue($link->save());
        $link->created_at = strtotime('-10 years');
        $this->assertTrue($link->save());
        $this->assertFalse(self::$user->isVerified());

        $verify = $this->getVerifyEmail();

        $user = $verify->verifyEmailWithToken($link->link, true);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertTrue(self::$user->isVerified());
    }

    private function getVerifyEmail(): VerifyEmailHelper
    {
        return new VerifyEmailHelper(self::getService('test.database'), self::getService('test.mailer'));
    }
}
