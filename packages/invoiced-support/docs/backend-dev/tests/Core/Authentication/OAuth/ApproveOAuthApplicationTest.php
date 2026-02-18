<?php

namespace App\Tests\Core\Auth\OAuth;

use App\Core\Authentication\OAuth\ApproveOAuthApplication;
use App\Core\Authentication\OAuth\Models\OAuthApplication;
use App\Core\Authentication\OAuth\ValueObjects\OAuthUser;
use App\Tests\AppTestCase;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Mockery;

class ApproveOAuthApplicationTest extends AppTestCase
{
    private static OAuthApplication $application;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$application = OAuthApplication::makeNewApp('approve_oauth_app', ['https://localhost/complete_oauth']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::getService('test.database')->executeStatement('DELETE FROM OAuthApplicationAuthorizations WHERE application_id='.self::$application->id());
        self::getService('test.database')->executeStatement('DELETE FROM OAuthAccessTokens WHERE application_id='.self::$application->id());
        self::getService('test.database')->executeStatement('DELETE FROM OAuthAuthorizationCodes WHERE application_id='.self::$application->id());
        self::$application->delete();
    }

    private function getAction(): ApproveOAuthApplication
    {
        return self::getService('test.approve_oauth_application');
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testApproveForUser(): void
    {
        $user = self::getService('test.user_context')->get();

        $action = $this->getAction();
        $request = Mockery::mock(AuthorizationRequest::class);
        $request->shouldReceive('setAuthorizationApproved')->withArgs([true]);
        $request->shouldReceive('setUser');
        $request->shouldReceive('getGrantTypeId')->andReturn('authorization_code');
        $request->shouldReceive('getUser')->andReturn(new OAuthUser($user));
        $request->shouldReceive('getRedirectUri')->andReturn(self::$application->redirect_uris[0]);
        $request->shouldReceive('getClient')->andReturn(self::$application);
        $request->shouldReceive('getScopes')->andReturn([]);
        $request->shouldReceive('isAuthorizationApproved')->andReturn(true);
        $request->shouldReceive('getCodeChallenge');
        $request->shouldReceive('getCodeChallengeMethod');
        $request->shouldReceive('getState');

        $action->approveForUser($user, $request);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testApproveForTenant(): void
    {
        $user = self::getService('test.user_context')->get();

        $action = $this->getAction();
        $request = Mockery::mock(AuthorizationRequest::class);
        $request->shouldReceive('setAuthorizationApproved')->withArgs([true]);
        $request->shouldReceive('setUser');
        $request->shouldReceive('getGrantTypeId')->andReturn('authorization_code');
        $request->shouldReceive('getUser')->andReturn(new OAuthUser($user));
        $request->shouldReceive('getRedirectUri')->andReturn(self::$application->redirect_uris[0]);
        $request->shouldReceive('getClient')->andReturn(self::$application);
        $request->shouldReceive('getScopes')->andReturn([]);
        $request->shouldReceive('isAuthorizationApproved')->andReturn(true);
        $request->shouldReceive('getCodeChallenge');
        $request->shouldReceive('getCodeChallengeMethod');
        $request->shouldReceive('getState');

        $action->approveForTenant($user, self::$company, $request);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDeny(): void
    {
        $user = self::getService('test.user_context')->get();

        $action = $this->getAction();
        $request = Mockery::mock(AuthorizationRequest::class);
        $request->shouldReceive('setAuthorizationApproved')->withArgs([false]);
        $request->shouldReceive('setUser');
        $request->shouldReceive('getGrantTypeId')->andReturn('authorization_code');
        $request->shouldReceive('getUser')->andReturn(new OAuthUser($user));
        $request->shouldReceive('getRedirectUri')->andReturn(self::$application->redirect_uris[0]);
        $request->shouldReceive('getClient')->andReturn(self::$application);
        $request->shouldReceive('isAuthorizationApproved')->andReturn(false);
        $request->shouldReceive('getCodeChallenge');
        $request->shouldReceive('getCodeChallengeMethod');
        $request->shouldReceive('getState');

        $action->deny($user, $request);
    }
}
