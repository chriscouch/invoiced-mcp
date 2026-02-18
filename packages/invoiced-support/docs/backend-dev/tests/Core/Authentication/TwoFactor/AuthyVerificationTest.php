<?php

namespace App\Tests\Core\Auth\TwoFactor;

use App\Core\Authentication\Exception\TwoFactorException;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\TwoFactor\AuthyVerification;
use App\Tests\AppTestCase;
use Authy\AuthyApi;
use Mockery;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthyVerificationTest extends AppTestCase
{
    protected function setUp(): void
    {
        $req = new Request();
        $stack = new RequestStack();
        $stack->push($req);
    }

    private function getStrategy(?AuthyApi $authy = null): AuthyVerification
    {
        $authy ??= Mockery::mock(AuthyApi::class);

        return new AuthyVerification($authy);
    }

    public function testRegisterFail(): void
    {
        $this->expectException(TwoFactorException::class);
        $this->expectExceptionMessage('Could not enroll in two-factor authentication: Phone is not valid');

        $errors = (object) ['Phone' => 'is not valid'];

        $authyUser = Mockery::mock();
        $authyUser->shouldReceive('ok')
            ->andReturn(false);
        $authyUser->shouldReceive('errors')
            ->andReturn($errors);

        $authy = Mockery::mock(AuthyApi::class);
        $authy->shouldReceive('registerUser')
            ->withArgs(['test@example.com', '1234567890', '1'])
            ->andReturn($authyUser);

        $strategy = $this->getStrategy($authy);

        $user = new User();
        $user->email = 'test@example.com';

        $strategy->register($user, '1234567890', 1);
    }

    public function testRegister(): void
    {
        $user = self::getService('test.user_context')->get();

        $authyUser = Mockery::mock();
        $authyUser->shouldReceive('ok')
            ->andReturn(true);
        $authyUser->shouldReceive('id')
            ->andReturn(1234);

        $authy = Mockery::mock(AuthyApi::class);
        $authy->shouldReceive('registerUser')
            ->withArgs([$user->email, '1234567890', '1'])
            ->andReturn($authyUser);

        $strategy = $this->getStrategy($authy);

        $strategy->register($user, '1234567890', 1);

        $this->assertEquals(1234, $user->authy_id);
        $this->assertFalse($user->verified_2fa);
    }

    /**
     * @depends testRegister
     */
    public function testVerifyFail(): void
    {
        $this->expectException(TwoFactorException::class);
        $this->expectExceptionMessage('Could not verify two-factor token: Token is not valid');

        $errors = (object) ['message' => 'Token is not valid'];

        $authyResp = Mockery::mock();
        $authyResp->shouldReceive('ok')
            ->andReturn(false);
        $authyResp->shouldReceive('errors')
            ->andReturn($errors);

        $authy = Mockery::mock(AuthyApi::class);
        $authy->shouldReceive('verifyToken')
            ->withArgs([1234, '1234567'])
            ->andReturn($authyResp);

        $strategy = $this->getStrategy($authy);

        $strategy->verify(self::getService('test.user_context')->get(), '1234567');

        $this->assertFalse(self::getService('test.user_context')->get()->verified_2fa);
    }

    /**
     * @depends testRegister
     */
    public function testVerify(): void
    {
        $user = self::getService('test.user_context')->get();

        $authyResp = Mockery::mock();
        $authyResp->shouldReceive('ok')
            ->andReturn(true);

        $authy = Mockery::mock(AuthyApi::class);
        $authy->shouldReceive('verifyToken')
            ->withArgs([1234, '1234567'])
            ->andReturn($authyResp);

        $strategy = $this->getStrategy($authy);

        $strategy->verify($user, '1234567');

        $this->assertTrue($user->verified_2fa);
    }

    /**
     * @doesNotPerformAssertions
     *
     * @depends testRegister
     */
    public function testRequestSMS(): void
    {
        $user = self::getService('test.user_context')->get();

        $authyResp = Mockery::mock();
        $authyResp->shouldReceive('ok')
            ->andReturn(true);

        $authy = Mockery::mock(AuthyApi::class);
        $authy->shouldReceive('requestSms')
            ->withArgs([1234, ['force' => true]])
            ->andReturn($authyResp);

        $strategy = $this->getStrategy($authy);

        $strategy->requestSMS($user);
    }

    /**
     * @depends testRegister
     */
    public function testDeregister(): void
    {
        $user = self::getService('test.user_context')->get();

        $authyResp = Mockery::mock();
        $authyResp->shouldReceive('ok')
            ->andReturn(true);

        $authy = Mockery::mock(AuthyApi::class);
        $authy->shouldReceive('deleteUser')
            ->withArgs([1234])
            ->andReturn($authyResp);

        $strategy = $this->getStrategy($authy);

        $strategy->deregister($user);

        $this->assertNull($user->authy_id);
        $this->assertFalse($user->verified_2fa);
    }
}
