<?php

namespace App\Tests\Core\Auth\LoginStrategy;

use App\Companies\FraudScore\IpAddressFraudScore;
use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\RememberMeHelper;
use App\Core\Authentication\LoginStrategy\UsernamePasswordLoginStrategy;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Models\UserLink;
use App\Core\Statsd\StatsdClient;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class UsernamePasswordLoginStrategyTest extends AppTestCase
{
    private static User $user;
    private static User $ogUser;
    private static string $email;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$ogUser = self::getService('test.user_context')->get();

        self::hasCompany();
        $member = self::hasMember(uniqid());
        self::$user = $member->user();
        self::$email = self::$user->email;
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

    public function testGetUserWithCredentialsBadUsername(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Please enter a valid username.');

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('', '');
    }

    public function testGetUserWithCredentialsBadPassword(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Please enter a valid password.');

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials(self::$email, '');
    }

    public function testGetUserWithCredentialsMissingUser(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('We could not find a match for that email address and password.');

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'type' => UserLink::TEMPORARY, ]));

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials('doesnotexist@example.com', 'TestPassw0rd!');
    }

    public function testGetUserWithCredentialsWrongPassword(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('We could not find a match for that email address and password.');

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'type' => UserLink::TEMPORARY, ]));

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials(self::$email, 'wrong password');
    }

    public function testGetUserWithCredentialsFailTemporary(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('It looks like your account has not been setup yet. Please go to the sign up page to finish creating your account.');

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());
        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'type' => UserLink::TEMPORARY, ]));

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials(self::$email, 'TestPassw0rd!');
    }

    public function testGetUserWithCredentialsFailDisabled(): void
    {
        self::getService('test.database')->delete('UserLinks', ['user_id' => self::$user->id()]);

        self::$user->enabled = false;
        $this->assertTrue(self::$user->save());

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Sorry, your account has been disabled.');

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials(self::$email, 'TestPassw0rd!');
    }

    public function testGetUserWithCredentialsFailNotVerified(): void
    {
        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());

        $link = new UserLink();
        $this->assertTrue($link->create([
            'user_id' => self::$user->id(),
            'type' => UserLink::VERIFY_EMAIL, ]));
        $link->created_at = (int) strtotime('-10 years');
        $this->assertTrue($link->save());

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('You must verify your account with the email that was sent to you before you can log in.');

        $strategy = $this->getStrategy();
        $strategy->getUserWithCredentials(self::$email, 'TestPassw0rd!');
    }

    public function testGetUserWithCredentials(): void
    {
        self::getService('test.database')->delete('UserLinks', ['user_id' => self::$user->id()]);

        self::$user->enabled = true;
        $this->assertTrue(self::$user->save());

        $strategy = $this->getStrategy();
        $user = $strategy->getUserWithCredentials(self::$email, 'TestPassw0rd!');

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals(self::$user->id(), $user->id());
    }

    public function testLoginFail(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('We could not find a match for that email address and password.');

        $strategy = $this->getStrategy();
        $strategy->login($this->getRequest(), self::$email, 'bogus');
    }

    public function testLogin(): void
    {
        $strategy = $this->getStrategy();
        $user = $strategy->login($this->getRequest(), self::$email, 'TestPassw0rd!');

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->isFullySignedIn());
        $this->assertEquals(self::$user->id(), $user->id());
        $this->assertEquals(self::$user->id(), self::getService('test.user_context')->get()->id());
    }

    public function testLoginVpnIpAddress(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Signing in to Invoiced with a VPN is not allowed.');

        $ipFraudScore = Mockery::mock(IpAddressFraudScore::class);
        $ipFraudScore->shouldReceive('calculateScore')->andReturn(50000);

        $this->getStrategy($ipFraudScore)->login($this->getRequest(), self::$email, 'TestPassw0rd!');
    }

    public function testLoginSamlDisabled(): void
    {
        $strategy = $this->getStrategy();

        $settings = new CompanySamlSettings();
        $settings->company = self::$company;
        $settings->domain = 'example.com';
        $settings->cert = 'test';
        $settings->entity_id = 1;
        $settings->sso_url = 'https://example.com';
        $settings->saveOrFail();

        /** @var User $user */
        $user = $strategy->login($this->getRequest(), self::$email, 'TestPassw0rd!');
        $this->assertEquals(self::$user->id, $user->id());

        $settings->enabled = true;
        $settings->saveOrFail();
        /** @var User $user */
        $user = $strategy->login($this->getRequest(), self::$email, 'TestPassw0rd!');
        $this->assertEquals(self::$user->id, $user->id());

        $settings->disable_non_sso = true;
        $settings->saveOrFail();
        try {
            $strategy->login($this->getRequest(), self::$email, 'TestPassw0rd!');
        } catch (AuthException $e) {
            $this->assertEquals('You do not have access to any companies.', $e->getMessage());
        }

        $settings->enabled = false;
        $settings->saveOrFail();
        /** @var User $user */
        $user = $strategy->login($this->getRequest(), self::$email, 'TestPassw0rd!');
        $this->assertEquals(self::$user->id, $user->id());
    }

    public function testLoginRateLimited(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('This account has been locked due to too many failed sign in attempts. The lock is only temporary. Please try again after 30 minutes.');

        $strategy = $this->getStrategy();
        $email = 'userdoesnotexist@example.com';

        // simulate 6 retries
        for ($i = 0; $i < 6; ++$i) {
            try {
                $strategy->login($this->getRequest(), $email, 'not the password');
            } catch (AuthException) {
                // ignore exceptions
            }
        }

        // the 7th should throw a locked out exception message
        $strategy->login($this->getRequest(), $email, 'not the password');
    }

    /**
     * @depends testLoginRateLimited
     */
    public function testLoginAfterRateLimiting(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('This account has been locked due to too many failed sign in attempts. The lock is only temporary. Please try again after 30 minutes.');

        $this->getStrategy()->login($this->getRequest(), 'userdoesnotexist@example.com', 'not the password');
    }

    public function testVerifyPassword(): void
    {
        $strategy = $this->getStrategy();
        $user = new User();

        $this->assertFalse($strategy->verifyPassword($user, ''));

        $user->password = password_hash('thisismypassword', PASSWORD_DEFAULT);
        $this->assertTrue($strategy->verifyPassword($user, 'thisismypassword'));
        $this->assertFalse($strategy->verifyPassword($user, 'thisisnotmypassword'));
        $this->assertFalse($strategy->verifyPassword($user, ''));
    }

    private function getStrategy(?IpAddressFraudScore $ipFraudScore = null): UsernamePasswordLoginStrategy
    {
        $ipLimiter = self::getService('test.rate_limiter_ip_login');
        $usernameLimiter = self::getService('test.rate_limiter_username_login');
        if (!$ipFraudScore) {
            $ipFraudScore = Mockery::mock(IpAddressFraudScore::class);
            $ipFraudScore->shouldReceive('calculateScore')->andReturn(0);
        }
        $loginHelper = self::getService('test.login_helper');
        $rememberMe = Mockery::mock(RememberMeHelper::class);
        $strategy = new UsernamePasswordLoginStrategy($usernameLimiter, $ipLimiter, $ipFraudScore, $rememberMe, $loginHelper);
        $strategy->setStatsd(new StatsdClient());

        return $strategy;
    }

    private function getRequest(): Request
    {
        return new Request([], [], [], [], [], ['REMOTE_ADDR' => $this->getIpAddress(), 'HTTP_USER_AGENT' => 'Firefox']);
    }

    private function getIpAddress(): string
    {
        return random_int(1, 255).'.'.random_int(1, 255).'.'.random_int(1, 255).'.'.random_int(1, 255);
    }
}
