<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\UserInvites;
use App\Core\Authentication\Models\User;
use App\Tests\AppTestCase;

class UserInvitesTest extends AppTestCase
{
    private static User $user;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::getService('test.database')->delete('Users', ['email' => 'test+auth@example.com']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$user->delete();
    }

    public function testInvite(): void
    {
        $invites = $this->getUserInvites();
        $user = $invites->invite('test+auth@example.com');

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->isTemporary());
    }

    public function testInviteExistingUser(): void
    {
        self::getService('test.database')->executeStatement('DELETE FROM UserLinks');

        $invites = $this->getUserInvites();
        /** @var User $user */
        $user = $invites->invite('test+auth@example.com');

        $this->assertInstanceOf(User::class, $user);
        self::$user = $user;
        $this->assertFalse($user->isTemporary());
        $this->assertEquals(self::$user->id(), $user->id());
    }

    public function testInviteFail(): void
    {
        $this->expectException(AuthException::class);

        $invites = $this->getUserInvites();
        $invites->invite('', []);
    }

    private function getUserInvites(): UserInvites
    {
        return new UserInvites(self::getService('test.user_registration'), self::getService('test.mailer'));
    }
}
