<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\UserRegistration;
use App\Core\Authentication\Libs\VerifyEmailHelper;
use App\Core\Authentication\Models\User;
use App\Core\Statsd\StatsdClient;
use App\Tests\AppTestCase;

class UserRegistrationTest extends AppTestCase
{
    private static User $user;
    private static User $user2;
    private static User $user3;
    private static User $user4;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::getService('test.database')->executeStatement('DELETE FROM Users WHERE email <> "test@example.com"');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$user->delete();
        self::$user2->delete();
        self::$user3->delete();
        self::$user4->delete();
    }

    public function testCreateFail(): void
    {
        $this->expectException(AuthException::class);
        $registrar = $this->getUserRegistration();
        $registrar->registerUser([], false, true);
    }

    public function testRegisterUser(): void
    {
        $registrar = $this->getUserRegistration();

        /** @var User $user */
        $user = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test+auth@example.com',
            'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
            'ip' => '127.0.0.1',
        ], false, true);
        self::$user = $user;

        $this->assertInstanceOf(User::class, self::$user);
        $this->assertGreaterThan(0, self::$user->id());
        $this->assertFalse(self::$user->isVerified(false));
    }

    public function testRegisterUserVerifiedEmail(): void
    {
        $registrar = $this->getUserRegistration();

        /** @var User $user */
        $user = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test2@example.com',
            'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
            'ip' => '127.0.0.1',
        ], true, true);
        self::$user2 = $user;

        $this->assertInstanceOf(User::class, self::$user2);
        $this->assertGreaterThan(0, self::$user2->id());
        $this->assertTrue(self::$user2->isVerified(false));
    }

    public function testCreateTemporaryFailNoEmail(): void
    {
        $this->expectException(AuthException::class);
        $registrar = $this->getUserRegistration();
        $registrar->createTemporaryUser([]);
    }

    public function testGetTemporaryUser(): void
    {
        $registrar = $this->getUserRegistration();
        $this->assertNull($registrar->getTemporaryUser('test+auth@example.com'));
    }

    public function testCreateTemporaryUser(): void
    {
        $registrar = $this->getUserRegistration();

        /** @var User $user */
        $user = $registrar->createTemporaryUser([
            'email' => 'test3@example.com',
            'password' => '',
            'first_name' => '',
            'last_name' => '',
            'ip' => '',
            'enabled' => true,
        ]);
        self::$user3 = $user;

        $this->assertInstanceOf(User::class, self::$user3);
        $this->assertTrue(self::$user3->isTemporary());

        $user = $registrar->getTemporaryUser('test3@example.com');
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user3->id(), $user->id());
    }

    /**
     * @depends testRegisterUser
     */
    public function testUpgradeTemporaryUserFail(): void
    {
        $this->expectException(AuthException::class);

        $registrar = $this->getUserRegistration();

        $registrar->upgradeTemporaryUser(self::$user, [
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
            'ip' => '127.0.0.1',
        ], true);
    }

    /**
     * @depends testCreateTemporaryUser
     */
    public function testUpgradeTemporaryUser(): void
    {
        $registrar = $this->getUserRegistration();

        $this->assertEquals($registrar, $registrar->upgradeTemporaryUser(self::$user3, [
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
            'ip' => '127.0.0.1',
        ], true));
    }

    /**
     * @depends testCreateTemporaryUser
     */
    public function testRegisterUserUpgradeTemporary(): void
    {
        $registrar = $this->getUserRegistration();

        /** @var User $user */
        $user = $registrar->createTemporaryUser([
            'email' => 'test4@example.com',
            'password' => '',
            'first_name' => '',
            'last_name' => '',
            'ip' => '',
            'enabled' => true,
        ]);
        self::$user4 = $user;

        /** @var User $upgradedUser */
        $upgradedUser = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'test4@example.com',
            'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
            'ip' => '127.0.0.1',
        ], false, true);

        $this->assertInstanceOf(User::class, $upgradedUser);
        $this->assertEquals(self::$user4->id(), $upgradedUser->id());
        $this->assertFalse($upgradedUser->isTemporary());
        $this->assertNull($registrar->getTemporaryUser('test4@example.com'));
    }

    private function getUserRegistration(): UserRegistration
    {
        $registration = new UserRegistration(new VerifyEmailHelper(self::getService('test.database'), self::getService('test.mailer')), self::getService('test.mailer'), self::getService('test.database'));
        $registration->setStatsd(new StatsdClient());

        return $registration;
    }
}
