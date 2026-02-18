<?php

namespace App\Tests\Core\Auth\LoginStrategy;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\LoginHelper;
use App\Core\Authentication\LoginStrategy\SamlV1LoginStrategy;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Saml\SamlAuthFactory;
use App\Core\Authentication\Saml\SamlResponseSimplified;
use App\Core\Statsd\StatsdClient;
use App\Tests\AppTestCase;
use Exception;
use Mockery;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use OneLogin\Saml2\Auth;
use Symfony\Bundle\FrameworkBundle\Test\WebTestAssertionsTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;

class SamlLoginStrategyTest extends AppTestCase
{
    use WebTestAssertionsTrait;

    private static SamlV1LoginStrategy $strategy;
    private static string $email;
    private static User $user;
    private static SessionInterface $mockSession;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$mockSession = Mockery::mock(SessionInterface::class);
        self::$mockSession->shouldReceive('set');

        self::$email = 'test+'.uniqid().'@example.com';
        self::hasCompany();
        self::$user = self::getService('test.user_registration')->registerUser([
            'first_name' => 'Bob',
            'last_name' => 'Loblaw',
            'email' => self::$email,
            'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
            'ip' => '127.0.0.1',
        ], false, false);
        $authFactory = Mockery::mock(SamlAuthFactory::class);
        $loginHelper = Mockery::mock(LoginHelper::class);
        $loginHelper->shouldReceive('signInUser');
        self::$strategy = Mockery::mock(SamlV1LoginStrategy::class, [$authFactory, $loginHelper]);
        self::$strategy->makePartial()->shouldAllowMockingProtectedMethods();
        self::$strategy->setStatsd(new StatsdClient());
        $urlGenerator = Mockery::mock(UrlGenerator::class);
        $urlGenerator->shouldReceive('generate')->andReturn('');
        $factory = new SamlAuthFactory($urlGenerator);
        $factory->setLogger(new Logger('test', [new NullHandler()]));
        self::$strategy->setFactory($factory);
    }

    public function testAuthenticate(): void
    {
        CompanySamlSettings::where('domain', 'x.com')->delete();
        $request = new Request([], ['email' => 'x@x.com']);

        try {
            self::$strategy->authenticate($request);

            throw new Exception('Auth Exception was not thrown');
        } catch (AuthException $e) {
            $this->assertEquals($e->getMessage(), 'Single sign-on is not configured');
        }
        $request = new Request([], ['email' => self::$email]);

        try {
            self::$strategy->authenticate($request);
        } catch (AuthException) {
            $this->assertTrue(true);
        }

        $settings = new CompanySamlSettings();
        $settings->company = self::$company;
        $settings->domain = 'example.com';
        $settings->cert = 'test';
        $settings->entity_id = 1;
        $settings->sso_url = 'https://example.com';
        $settings->saveOrFail();

        try {
            self::$strategy->authenticate($request);
        } catch (AuthException) {
            $this->assertTrue(true);
        }

        $settings->enabled = true;
        $settings->saveOrFail();
        try {
            self::$strategy->authenticate($request);
        } catch (AuthException $e) {
            $this->assertEquals('Invalid array settings: sp_acs_not_found, sp_sls_url_invalid', $e->getMessage());
        }
    }

    public function testDoSignIn(): void
    {
        CompanySamlSettings::where('company_id', self::$company->id())->delete();

        self::$strategy->setLogger(new Logger('test', [new NullHandler()]));
        /* @phpstan-ignore-next-line */
        self::$strategy->shouldReceive('postValidateUser')
            ->andReturn(true);
        /* @phpstan-ignore-next-line */
        self::$mockSession->shouldReceive('get')
            ->andReturn('example.com');

        $request = new Request();
        $request->setSession(self::$mockSession);

        $samlResponse = $this->getSamlResponse();

        // not set up
        try {
            self::$strategy->doSignIn($request, self::$user, $samlResponse);
            throw new Exception('SSO Exception was not thrown');
        } catch (AuthException $e) {
            $this->assertEquals('Single sign-on is not configured', $e->getMessage());
        }

        $settings = new CompanySamlSettings();
        $settings->company = self::$company;
        $settings->domain = 'example.com';
        $settings->entity_id = 1;
        $settings->sso_url = 'test';
        $settings->cert = 'test';
        $settings->saveOrFail();

        // disabled
        try {
            self::$strategy->doSignIn($request, self::$user, $samlResponse);
            throw new Exception('SSO Exception was not thrown');
        } catch (AuthException $e) {
            $this->assertEquals($e->getMessage(), 'Single sign-on is disabled');
        }

        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processResponse');
        $auth->shouldReceive('getErrors')->andReturn([]);
        $auth->shouldReceive('isAuthenticated')->twice();
        $factory = Mockery::mock(SamlAuthFactory::class);
        self::$strategy->setFactory($factory);

        $settings->enabled = true;
        $settings->saveOrFail();
        $factory->shouldReceive('get')->andReturn($auth);
        // no email and id
        try {
            self::$strategy->doSignIn($request, self::$user, $samlResponse);
            throw new Exception('Auth Exception was not thrown');
        } catch (AuthException $e) {
            $this->assertEquals($e->getMessage(), 'Not authenticated');
        }

        try {
            self::$strategy->doSignIn($request, self::$user, $samlResponse);
            throw new Exception('Auth Exception was not thrown');
        } catch (AuthException $e) {
            $this->assertEquals($e->getMessage(), 'Not authenticated');
        }

        $auth->shouldReceive('isAuthenticated')->andReturn(true);
        self::$strategy->doSignIn($request, self::$user, $samlResponse);

        $user = new User();
        $user->email = 'nonexistentuser@example.com';
        try {
            self::$strategy->doSignIn($request, $user, $samlResponse);
            throw new Exception('Auth Exception was not thrown');
        } catch (AuthException $e) {
            $this->assertEquals('It looks like your account has not been setup yet. Please go to the sign up page to finish creating your account.', $e->getMessage());
        }

        self::$strategy->doSignIn($request, self::$user, $samlResponse);
    }

    public function testExceptions(): void
    {
        $this->assertEquals('saml', self::$strategy->getId());
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('processResponse')
            ->andThrow(new Exception('SSO Error when Processing Response'))
            ->once();

        $authFactory = Mockery::mock(SamlAuthFactory::class);
        $authFactory->shouldReceive('get')
            ->andReturn($auth);

        $samlResponse = $this->getSamlResponse();

        self::$strategy->setFactory($authFactory);
        try {
            self::$strategy->doSignIn(new Request(), self::$user, $samlResponse);
            $this->assertFalse(true, 'No exception thrown');
        } catch (AuthException $e) {
            $this->assertEquals('The IdP request is malformed', $e->getMessage());
        }

        $auth->shouldReceive('processResponse')
            ->andReturnNull();
        $auth->shouldReceive('getErrors')
            ->andReturn(['test', 'test2'])
            ->once();
        $auth->shouldReceive('getLastErrorReason')
            ->andReturnNull();
        $auth->shouldReceive('getLastErrorException')
            ->andReturnNull();

        try {
            self::$strategy->doSignIn(new Request(), self::$user, $samlResponse);
            $this->assertFalse(true, 'No exception thrown');
        } catch (AuthException $e) {
            $this->assertEquals('test, test2', $e->getMessage());
        }

        $auth->shouldReceive('getErrors')
            ->andReturn([]);
        $auth->shouldReceive('isAuthenticated')
            ->andReturnFalse();

        try {
            self::$strategy->doSignIn(new Request(), self::$user, $samlResponse);
            $this->assertFalse(true, 'No exception thrown');
        } catch (AuthException $e) {
            $this->assertEquals('Not authenticated', $e->getMessage());
        }
    }

    private function getSamlResponse(): SamlResponseSimplified
    {
        $response = Mockery::mock(SamlResponseSimplified::class);
        $response->shouldReceive('getSessionNotOnOrAfter')->andReturn(strtotime('+1 day'));

        return $response;
    }
}
