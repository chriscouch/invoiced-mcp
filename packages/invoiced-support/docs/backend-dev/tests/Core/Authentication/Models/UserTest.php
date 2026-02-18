<?php

namespace App\Tests\Core\Auth\Models;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\ActiveSession;
use App\Core\Authentication\Models\PersistentSession;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Models\UserLink;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Event\ModelUpdating;
use App\Core\Orm\Exception\ListenerException;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class UserTest extends AppTestCase
{
    private static User $user;
    private static User $user2;
    private static User $ogUser;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::$ogUser = self::getService('test.user_context')->get();

        self::getService('test.database')->executeStatement('DELETE FROM Users WHERE email LIKE "user%"');

        self::getService('test.tenant')->clear();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (isset(self::$user)) {
            self::$user->delete();
        }
        if (isset(self::$user2)) {
            self::$user2->delete();
        }
    }

    private function getRequest(): Request
    {
        return new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'Firefox']);
    }

    public function assertPostConditions(): void
    {
        self::getService('test.user_context')->set(self::$ogUser);
    }

    public function testRegisterUserMissingPassword(): void
    {
        $user = new User();
        $user->first_name = 'Bob';
        $user->last_name = 'Loblaw';
        $user->email = 'test.missingpass@example.com';
        $user->ip = '127.0.0.1';
        $this->assertFalse($user->save());

        $this->assertEquals(['Password is missing'], $user->getErrors()->all());
    }

    public function testRegisterUserPasswordNotMatching(): void
    {
        $user = new User();
        $user->first_name = 'Bob';
        $user->last_name = 'Loblaw';
        $user->email = 'test.passnomatch@example.com';
        $user->ip = '127.0.0.1';
        $user->password = ['x*qo4uXG2YNzGpX', 'x*qo4uXG2YNzGpY']; /* @phpstan-ignore-line */
        $this->assertFalse($user->save());

        $this->assertEquals(['The supplied passwords do not match. Please check that you have entered in the correct password.'], $user->getErrors()->all());
    }

    public function testRegisterUserPasswordDoesNotMeetPolicy(): void
    {
        $user = new User();
        $user->first_name = 'Bob';
        $user->last_name = 'Loblaw';
        $user->email = 'test.weakpass@example.com';
        $user->ip = '127.0.0.1';
        $user->password = ['passwor', 'passwor']; /* @phpstan-ignore-line */
        $this->assertFalse($user->save());

        $msg = 'The supplied password did not meet the password policy. Please correct the following issues:
- Expecting a password length of at least 8 characters
- Expecting at least 1 uppercase characters
- Expecting at least 1 digit characters
- Expecting at least 1 symbol characters';
        $this->assertEquals([$msg], $user->getErrors()->all());
    }

    public function testRegisterUser(): void
    {
        $registrar = self::getService('test.user_registration');

        self::$user = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'user.test@example.com',
            'password' => ['x*qo4uXG2YNzGpX', 'x*qo4uXG2YNzGpX'],
            'ip' => '127.0.0.1',
        ], false, false);

        $this->assertInstanceOf(User::class, self::$user);
        $this->assertGreaterThan(0, self::$user->id());
        $this->assertTrue(self::$user->registered);
        $this->assertTrue(password_verify('x*qo4uXG2YNzGpX', self::$user->password));

        self::$user2 = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'user.test2@example.com',
            'password' => ['3dcCzi3tQYBQ#AU', '3dcCzi3tQYBQ#AU'],
            'ip' => '127.0.0.1',
            'invited' => false,
        ], true, false);

        $this->assertInstanceOf(User::class, self::$user2);
        $this->assertGreaterThan(0, self::$user2->id());
        $this->assertTrue(self::$user2->registered);
        $this->assertTrue(password_verify('3dcCzi3tQYBQ#AU', self::$user2->password));
    }

    /**
     * @depends testRegisterUser
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$user->id(),
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'user.test@example.com',
            'two_factor_enabled' => false,
            'registered' => true,
            'created_at' => self::$user->created_at,
            'updated_at' => self::$user->updated_at,
        ];

        $this->assertEquals($expected, self::$user->toArray());
    }

    public function testName(): void
    {
        $user = new User(['id' => 10]);
        $user->first_name = 'Bob';
        $user->last_name = 'Loblaw';
        $this->assertEquals('Bob', $user->name());
        $this->assertEquals('Bob Loblaw', $user->name(true));

        $guest = new User(['id' => -1]);
        $this->assertEquals('(not registered)', $guest->name());

        $notfound = new User(['id' => -100]);
        $this->assertEquals('(not registered)', $notfound->name());

        $user->first_name = '';
        $user->email = 'user.test@example.com';
        $this->assertEquals('user.test@example.com', $user->name());
    }

    /**
     * @depends testRegisterUser
     *
     * @doesNotPerformAssertions
     */
    public function testEditEmail(): void
    {
        ACLModelRequester::set(self::$user);
        self::$user->ip = '127.0.0.2';
        $editUser = self::getService('test.edit_user_protected_fields');
        $currentPassword = 'x*qo4uXG2YNzGpX';
        $parameters = [
            'email' => 'user.test3@example.com',
        ];
        $request = $this->getRequest();
        $editUser->change(self::$user, $request, $currentPassword, $parameters);
    }

    /**
     * @depends testRegisterUser
     */
    public function testEditProtectedFieldFail(): void
    {
        $this->expectException(AuthException::class);
        ACLModelRequester::set(self::$user);
        $editUser = self::getService('test.edit_user_protected_fields');
        $currentPassword = 'not correct';
        $parameters = [
            'email' => 'user.test4@example.com',
        ];
        $request = $this->getRequest();
        $editUser->change(self::$user, $request, $currentPassword, $parameters);
    }

    /**
     * @depends testRegisterUser
     */
    public function testEditWeakPassword(): void
    {
        self::getService('test.user_context')->set(self::$user);
        self::$user->password = 'weak';
        $this->assertFalse(self::$user->save());
    }

    /**
     * @depends testRegisterUser
     */
    public function testEditPassword(): void
    {
        $editUser = self::getService('test.edit_user_protected_fields');
        $request = $this->getRequest();
        self::getService('test.login_helper')->signInUser($request, self::$user, 'web');

        // create sessions
        $session = new ActiveSession();
        $session->id = 'sesh_1234';
        $session->user_id = (int) self::$user->id();
        $session->ip = '127.0.0.1';
        $session->user_agent = 'Firefox';
        $session->expires = strtotime('+1 month');
        $this->assertTrue($session->save());

        $persistent = new PersistentSession();
        $persistent->user_id = (int) self::$user->id();
        $persistent->series = str_repeat('a', 128);
        $persistent->token = str_repeat('a', 128);
        $this->assertTrue($persistent->save());

        self::getService('test.login_helper')->signInUser($request, self::$user, 'web');

        // change the password
        $currentPassword = 'x*qo4uXG2YNzGpX';
        $parameters = [
            'password' => 'TestPassw0rd!2',
            'email' => '',
        ];
        $editUser->change(self::$user, $request, $currentPassword, $parameters);

        // should create a security event
        $n = AccountSecurityEvent::where('user_id', self::$user->id())
            ->where('type', 'user.change_password')
            ->count();
        $this->assertEquals(1, $n);

        // should sign user out everywhere
        $n = ActiveSession::where('id', 'sesh_1234')
            ->where('valid', false)
            ->count();
        $this->assertEquals(1, $n);

        $n = PersistentSession::where('user_id', self::$user->id())->count();
        $this->assertEquals(0, $n);

        $this->assertNull(self::getService('test.user_context')->get());
    }

    /**
     * @depends testRegisterUser
     */
    public function testIsTemporary(): void
    {
        $this->assertFalse(self::$user->isTemporary());
    }

    /**
     * @depends testRegisterUser
     */
    public function testIsVerified(): void
    {
        $this->assertFalse(self::$user->isVerified(false));
        $this->assertTrue(self::$user->isVerified(true));

        UserLink::where('user_id', self::$user->id())
            ->where('type', UserLink::VERIFY_EMAIL)
            ->delete();

        $this->assertTrue(self::$user->isVerified());

        $this->assertTrue(self::$user2->isVerified());
    }

    public function testIsFullySignedIn(): void
    {
        $user = new User(['id' => 10]);
        $this->assertFalse($user->isFullySignedIn());
        $this->assertEquals($user, $user->setIsFullySignedIn());
        $this->assertTrue($user->isFullySignedIn());
        $this->assertEquals($user, $user->setIsFullySignedIn(false));
        $this->assertFalse($user->isFullySignedIn());
    }

    public function testIsTwoFactorVerified(): void
    {
        $user = new User(['id' => 10]);
        $this->assertFalse($user->isTwoFactorVerified());
        $this->assertEquals($user, $user->markTwoFactorVerified());
        $this->assertTrue($user->isTwoFactorVerified());
    }

    public function testProfilePicture(): void
    {
        $user = new User();
        $user->email = 'test@example.com';
        $this->assertEquals('https://secure.gravatar.com/avatar/55502f40dc8b7c769880b10874abc9d0?s=200&d=mm', $user->profilePicture());
    }

    /**
     * @depends testRegisterUser
     *
     * @doesNotPerformAssertions
     */
    public function testSendEmail(): void
    {
        self::getService('test.mailer')->sendToUser(self::$user, [], 'welcome');
        self::getService('test.mailer')->sendToUser(self::$user, [], 'verify-email', ['verify' => 'test']);
        self::getService('test.mailer')->sendToUser(self::$user, [], 'forgot-password', ['forgot' => 'test', 'ip' => 'test']);
    }

    /**
     * @depends testRegisterUser
     * @depends testEditPassword
     */
    public function testGetSupportPin(): void
    {
        $pin = self::$user->getSupportPin();
        $this->assertEquals(8, strlen((string) $pin));
        $this->assertTrue(is_numeric($pin));

        $this->assertEquals($pin, self::$user->getSupportPin());
    }

    /**
     * @depends testRegisterUser
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$user->delete());
    }

    /**
     * @depends testDelete
     */
    public function testRegisterUserTemporary(): void
    {
        $registrar = self::getService('test.user_registration');

        self::$user = $registrar->createTemporaryUser(['email' => 'user.test@example.com']);

        $this->assertInstanceOf(User::class, self::$user);
        $this->assertTrue(self::$user->isTemporary());
        $this->assertFalse(self::$user->registered);

        $upgradedUser = $registrar->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => 'user.test@example.com',
            'password' => ['bTXP3Pq?fXur8AX', 'bTXP3Pq?fXur8AX'],
            'ip' => '127.0.0.1',
        ], false, false);

        $this->assertInstanceOf(User::class, $upgradedUser);
        $this->assertEquals(self::$user->id(), $upgradedUser->id());
        $this->assertFalse($upgradedUser->isTemporary());
    }

    public function testValidatePassword(): void
    {
        $model = \Mockery::mock(User::class)->makePartial();
        $model->shouldReceive('dirty')->andReturn(true);

        $event = new ModelUpdating($model);

        $model->password = ['test', 'nottest']; /* @phpstan-ignore-line */
        try {
            User::validatePassword($event);
            throw new \Exception('Exception not thrown');
        } catch (ListenerException $e) {
            $this->assertEquals('The supplied passwords do not match. Please check that you have entered in the correct password.', $e->getMessage());
        }

        $model->password = ['test', 'test']; /* @phpstan-ignore-line */
        try {
            User::validatePassword($event);
            throw new \Exception('Exception not thrown');
        } catch (ListenerException $e) {
            $this->assertEquals(5, count(explode("\n", $e->getMessage())));
        }
        $model->password = ['Test', 'Test']; /* @phpstan-ignore-line */
        try {
            User::validatePassword($event);
            throw new \Exception('Exception not thrown');
        } catch (ListenerException $e) {
            $this->assertEquals(4, count(explode("\n", $e->getMessage())));
        }
        $model->password = ['Test1', 'Test1']; /* @phpstan-ignore-line */
        try {
            User::validatePassword($event);
            throw new \Exception('Exception not thrown');
        } catch (ListenerException $e) {
            $this->assertEquals(3, count(explode("\n", $e->getMessage())));
        }
        $model->password = ['Test1!', 'Test1!']; /* @phpstan-ignore-line */
        try {
            User::validatePassword($event);
            throw new \Exception('Exception not thrown');
        } catch (ListenerException $e) {
            $this->assertEquals(2, count(explode("\n", $e->getMessage())));
        }

        $model->password = ['Test1!22', 'Test1!22']; /* @phpstan-ignore-line */
        $model->shouldReceive('ignoreUnsaved')->andReturnUsing(function () use ($model) {
            $model->password = password_hash('Test1!22', PASSWORD_DEFAULT);

            return $model;
        });
        try {
            User::validatePassword($event);
            throw new \Exception('Exception not thrown');
        } catch (ListenerException $e) {
            $this->assertEquals('The supplied password should not match current password', $e->getMessage());
        }

        User::validatePassword($event);

        $this->assertTrue(true);
    }
}
