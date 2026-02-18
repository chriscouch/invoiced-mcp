<?php

namespace App\Tests\Core\RestApi;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Authentication\Models\User;
use App\Core\Billing\Enums\BillingSubscriptionStatus;
use App\Core\Orm\ACLModelRequester;
use App\Core\Orm\Model;
use App\Core\RestApi\Exception\ApiHttpException;
use App\Core\RestApi\Libs\ApiKeyAuth;
use App\Core\RestApi\Models\ApiKey;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\HttpFoundation\Request;

class ApiKeyAuthTest extends AppTestCase
{
    private static ApiKey $apiKey;
    private static ApiKey $apiKey2;
    private static ApiKey $apiKey3;
    private static ApiKeyAuth $auth;
    private static ?Model $requester;
    private static User $user;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::$requester = ACLModelRequester::get();
        self::$user = self::getService('test.user_context')->get();

        // create an api key
        self::$apiKey = new ApiKey();
        self::$apiKey->save();

        // create a protected api key w/ a user
        self::$apiKey2 = new ApiKey();
        self::$apiKey2->user_id = (int) self::$user->id();
        self::$apiKey2->protected = true;
        self::$apiKey2->save();

        // create a protected api key witout a user
        self::$apiKey3 = new ApiKey();
        self::$apiKey3->protected = true;
        self::$apiKey3->save();

        self::$auth = self::getService('test.api_key_auth');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        self::getService('test.user_context')->set(self::$user);
        ACLModelRequester::set(self::$requester);
    }

    public function testGetOrgFromUsername(): void
    {
        $this->assertNull(self::$auth->getOrgFromUsername('test'));

        $org = self::$auth->getOrgFromUsername(self::$company->username);
        $this->assertInstanceOf(Company::class, $org);
        $this->assertEquals(self::$company->id(), $org->id());
    }

    public function testHandleRequestInvalidUsername(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('We did not find an account matching the username: blah');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn('blah');
        $request->shouldReceive('getPassword')
            ->andReturn(self::$apiKey->secret);

        self::$auth->handleRequest($request);
    }

    public function testHandleRequestInvalidSecret(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('We could not authenticate you with the API Key: abc**********nop');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$company->username);
        $request->shouldReceive('getPassword')
            ->andReturn('abcdefghijklmnop');

        self::$auth->handleRequest($request);
    }

    public function testHandleRequest(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$apiKey2->secret);
        $request->shouldReceive('getPassword')
            ->andReturn('');

        self::$auth->handleRequest($request);

        $this->assertTrue(self::getService('test.tenant')->has());
        $this->assertEquals(self::$company->id(), self::getService('test.tenant')->get()->id());

        $requester = ACLModelRequester::get();
        $this->assertInstanceOf(Member::class, $requester);
        $this->assertEquals(self::$user->id(), $requester->user_id);
        $this->assertEquals(self::$company->id(), $requester->tenant_id);

        $this->assertInstanceOf(User::class, self::getService('test.user_context')->get());
        $this->assertEquals(self::$user->id(), self::getService('test.user_context')->get()->id());
    }

    public function testHandleRequestSecretOnly(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$apiKey->secret);
        $request->shouldReceive('getPassword')
            ->andReturn('');

        self::$auth->handleRequest($request);

        $this->assertTrue(self::getService('test.tenant')->has());
        $this->assertEquals(self::$company->id(), self::getService('test.tenant')->get()->id());

        $requester = ACLModelRequester::get();
        $this->assertInstanceOf(Company::class, $requester);
        $this->assertEquals(self::$company->id(), $requester->id());

        $this->assertInstanceOf(User::class, self::getService('test.user_context')->get());
        $this->assertEquals(-3, self::getService('test.user_context')->get()->id());

        $apiKey = self::$auth->getCurrentApiKey();
        $this->assertInstanceOf(ApiKey::class, $apiKey);
        $this->assertEquals(self::$apiKey->id(), $apiKey->id());
    }

    public function testHandleRequestNoUser(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$apiKey->secret);
        $request->shouldReceive('getPassword')
            ->andReturn('');

        self::$auth->handleRequest($request);

        $this->assertTrue(self::getService('test.tenant')->has());
        $this->assertEquals(self::$company->id(), self::getService('test.tenant')->get()->id());

        $requester = ACLModelRequester::get();
        $this->assertInstanceOf(Company::class, $requester);
        $this->assertEquals(self::$company->id(), $requester->id());

        $this->assertInstanceOf(User::class, self::getService('test.user_context')->get());
        $this->assertEquals(-3, self::getService('test.user_context')->get()->id());
    }

    public function testHandleRequestNoUserProtected(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$apiKey3->secret);
        $request->shouldReceive('getPassword')
            ->andReturn('');

        self::$auth->handleRequest($request);

        $this->assertTrue(self::getService('test.tenant')->has());
        $this->assertEquals(self::$company->id(), self::getService('test.tenant')->get()->id());

        $requester = ACLModelRequester::get();
        $this->assertInstanceOf(Company::class, $requester);
        $this->assertEquals(self::$company->id(), $requester->id());

        $this->assertInstanceOf(User::class, self::getService('test.user_context')->get());
        $this->assertEquals(-2, self::getService('test.user_context')->get()->id());
    }

    public function testHandleRequestExpiringSoon(): void
    {
        self::$apiKey->expires = strtotime('+19 minutes');
        self::$apiKey->saveOrFail();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$apiKey->secret);
        $request->shouldReceive('getPassword')
            ->andReturn('');

        self::$auth->handleRequest($request);

        $this->assertTrue(self::getService('test.tenant')->has());
        $this->assertEquals(self::$company->id(), self::getService('test.tenant')->get()->id());

        $requester = ACLModelRequester::get();
        $this->assertInstanceOf(Company::class, $requester);
        $this->assertEquals(self::$company->id(), $requester->id());

        $this->assertInstanceOf(User::class, self::getService('test.user_context')->get());
        $this->assertEquals(-3, self::getService('test.user_context')->get()->id());
    }

    public function testHandleRequestExpiringSoonRememberMe(): void
    {
        $t = strtotime('+19 minutes');
        self::$apiKey->expires = $t;
        self::$apiKey->remember_me = true;
        self::$apiKey->saveOrFail();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$apiKey->secret);
        $request->shouldReceive('getPassword')
            ->andReturn('');

        self::$auth->handleRequest($request);

        $this->assertTrue(self::getService('test.tenant')->has());
        $this->assertEquals(self::$company->id(), self::getService('test.tenant')->get()->id());

        $requester = ACLModelRequester::get();
        $this->assertInstanceOf(Company::class, $requester);
        $this->assertEquals(self::$company->id(), $requester->id());

        $this->assertInstanceOf(User::class, self::getService('test.user_context')->get());
        $this->assertEquals(-3, self::getService('test.user_context')->get()->id());
    }

    public function testHandleNoLongerMember(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('We could not authenticate you with the API Key');

        // set the company to trial to ended
        $member = Member::where('user_id', self::$user->id())->one();
        $member->delete();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$company->username);
        $request->shouldReceive('getPassword')
            ->andReturn(self::$apiKey2->secret);

        self::$auth->handleRequest($request);
    }

    public function testHandleCanceledAccount(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('This account has been canceled. Please contact us at support@invoiced.com or visit https://app.invoiced.com to reactivate it.');

        // set the company to canceled
        self::$company->canceled = true;
        self::$company->saveOrFail();

        $this->assertEquals(BillingSubscriptionStatus::Canceled, self::$company->billingStatus());

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$company->username);
        $request->shouldReceive('getPassword')
            ->andReturn(self::$apiKey->secret);

        self::$auth->handleRequest($request);
    }

    public function testHandleTrialEnded(): void
    {
        $this->expectException(ApiHttpException::class);
        $this->expectExceptionMessage('Your trial has ended. Please contact us at support@invoiced.com or visit https://app.invoiced.com to subscribe.');

        // set the company trial to ended
        self::$company->set([
            'canceled' => false,
            'renews_next' => 0,
            'trial_ends' => strtotime('-1 month'),
        ]);

        $this->assertEquals(BillingSubscriptionStatus::Unpaid, self::$company->billingStatus());

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getUser')
            ->andReturn(self::$company->username);
        $request->shouldReceive('getPassword')
            ->andReturn(self::$apiKey->secret);

        self::$auth->handleRequest($request);
    }

    public function testUpdateKeyUsage(): void
    {
        self::$auth->updateKeyUsage(self::$apiKey2);

        $this->assertLessThan(3, abs(self::$apiKey2->last_used - time()));
    }

    public function testUpdateKeyUsageExpiringSoon(): void
    {
        $t = strtotime('+19 minutes');
        self::$apiKey->expires = $t;
        self::$apiKey->saveOrFail();

        $timestamp = time();
        self::$auth->updateKeyUsage(self::$apiKey);

        $this->assertGreaterThan($t, self::$apiKey->expires);
        $this->assertGreaterThanOrEqual($timestamp + 30 * 60, self::$apiKey->expires);
    }

    public function testUpdateKeyUsageExpiringSoonRememberMe(): void
    {
        self::$apiKey->expires = strtotime('+19 minutes');
        self::$apiKey->remember_me = true;
        self::$apiKey->saveOrFail();

        $timestamp = time();
        self::$auth->updateKeyUsage(self::$apiKey);

        $this->assertGreaterThanOrEqual($timestamp + 86400 * 3, self::$apiKey->refresh()->expires);
    }
}
