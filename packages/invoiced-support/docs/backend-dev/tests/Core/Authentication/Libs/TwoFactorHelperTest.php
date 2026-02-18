<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Interfaces\StorageInterface;
use App\Core\Authentication\Interfaces\TwoFactorInterface;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\Libs\RememberMeHelper;
use App\Core\Authentication\Libs\TwoFactorHelper;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Storage\SessionStorage;
use App\Core\Statsd\StatsdClient;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TwoFactorHelperTest extends AppTestCase
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

    private function getHelper(?TwoFactorInterface $twoFactor = null, ?StorageInterface $storage = null): TwoFactorHelper
    {
        $twoFactor ??= Mockery::mock(TwoFactorInterface::class);
        $storage ??= new SessionStorage(self::getService('test.database'));
        $rememberMe = Mockery::mock(RememberMeHelper::class);

        $twoFactorHelper = new TwoFactorHelper($twoFactor, $storage, $this->getLoginHelper($storage), $rememberMe);
        $twoFactorHelper->setStatsd(new StatsdClient());

        return $twoFactorHelper;
    }

    private function getLoginHelper(?StorageInterface $storage = null, ?UserContext $userContext = null): LoginHelper
    {
        $storage ??= new SessionStorage(self::getService('test.database'));
        $requestStack = Mockery::mock(RequestStack::class);
        $loginHelper = Mockery::mock(LoginHelper::class);
        $userContext ??= new UserContext($requestStack, $loginHelper);
        $rememberMe = Mockery::mock(RememberMeHelper::class);
        $twoFactorHelper = Mockery::mock(TwoFactorHelper::class);

        $loginHelper = new LoginHelper(self::getService('test.database'), $storage, $userContext, self::getService('event_dispatcher'), $rememberMe, $twoFactorHelper);
        $loginHelper->setStatsd(new StatsdClient());

        return $loginHelper;
    }

    private function getRequest(): Request
    {
        return new Request([], [], [], [], [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'infuse/1.0']);
    }

    public function testNeedsVerification(): void
    {
        $helper = $this->getHelper();
        $user = new User();
        $request = $this->getRequest();
        $this->assertFalse($helper->needsVerification($user, $request));

        $user->authy_id = '1234';
        $this->assertFalse($helper->needsVerification($user, $request));

        $user->verified_2fa = true;
        $this->assertTrue($helper->needsVerification($user, $request));
    }

    public function testVerify(): void
    {
        $user = self::$user;
        $user->setIsFullySignedIn(false);

        $strategy = Mockery::mock(TwoFactorInterface::class);
        $strategy->shouldReceive('verify')
            ->withArgs([$user, 'token'])
            ->once();

        $storage = Mockery::mock(StorageInterface::class);
        $storage->shouldReceive('markTwoFactorVerified')
            ->andReturn(true)
            ->once();
        $helper = $this->getHelper($strategy, $storage);

        $request = $this->getRequest();

        $helper->verify($request, $user, 'token', false);

        $this->assertTrue($user->isTwoFactorVerified());
        $this->assertTrue($user->isFullySignedIn());
    }

    public function testVerifyException(): void
    {
        $this->expectException(AuthException::class);

        $user = new User(['id' => 10]);

        $strategy = Mockery::mock(TwoFactorInterface::class);
        $strategy->shouldReceive('verify')
            ->andThrow(new AuthException('fail'));
        $helper = $this->getHelper($strategy);

        $request = $this->getRequest();

        $helper->verify($request, $user, 'token', false);
    }
}
