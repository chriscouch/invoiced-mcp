<?php

namespace App\Tests\Core\Auth\LoginStrategy;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\LoginStrategy\GoogleLoginStrategy;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Tests\AppTestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Mockery;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GoogleLoginStrategyTest extends AppTestCase
{
    private const JWT_KEY = 'example_key';

    private static User $ogUser;
    private static User $user;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        foreach (['test', 'test65498'] as $id) {
            self::getService('test.database')->delete('Users', ['google_claimed_id' => $id]);
        }
        self::$ogUser = self::getService('test.user_context')->get();

        $member = self::hasMember('1');
        self::$user = $member->user();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::getService('test.user_context')->set(self::$ogUser);
        self::$user->delete();
    }

    private function getStrategy(): GoogleLoginStrategy
    {
        $strategy = self::getService('test.google_login_strategy');
        $strategy->setKey(new Key(self::JWT_KEY, 'HS256'));

        return $strategy;
    }

    private function getIdToken(array $payload): string
    {
        return JWT::encode($payload, self::JWT_KEY, 'HS256');
    }

    private function getRequest(): Request
    {
        $request = new Request([], [], [], [], [], ['HTTP_USER_AGENT' => 'agent', 'REMOTE_ADDR' => '127.0.0.1']);
        $session = Mockery::mock(SessionInterface::class);
        $session->shouldReceive('set');
        $session->shouldReceive('getName')->andReturn('test');
        $request->setSession($session);

        return $request;
    }

    public function testGetId(): void
    {
        $this->assertEquals('google', $this->getStrategy()->getId());
    }

    public function testGetAuthorizationUrl(): void
    {
        $endpoint = $this->getStrategy()->getAuthorizationUrl('test_state');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $endpoint);

        /** @var array $parsed */
        $parsed = parse_url($endpoint);

        $expected = [
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'client_id' => 'id_test',
            'state' => 'test_state',
            'redirect_uri' => 'https://invoiced.localhost:1234/auth/google',
            'access_type' => 'online',
            'approval_prompt' => 'auto',
        ];

        parse_str($parsed['query'], $query);
        $this->assertEquals($expected, $query);
    }

    public function testHandleAuthorizationCodeInvalidAuthCode(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Could not create access token: Test');

        $client = new MockHttpClient([
            new MockResponse((string) json_encode(['error_description' => 'Test']), ['http_code' => 400]),
        ]);

        $strategy = $this->getStrategy();
        $strategy->setHttpClient($client);

        $request = $this->getRequest();

        $strategy->handleAuthorizationCode('code', $request);
    }

    public function testHandleAuthorizationCodeVerifyIdTokenFail(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Sorry, we were unable to verify your ID token. Please try again.');

        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                'access_token' => 'accessToken',
                'id_token' => 'not a valid token',
            ])),
        ]);

        $strategy = $this->getStrategy();
        $strategy->setHttpClient($client);

        $request = $this->getRequest();

        $strategy->handleAuthorizationCode('code', $request);
    }

    public function testHandleAuthorizationCodeNewUser(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                'access_token' => 'accessToken',
                'id_token' => $this->getIdToken([
                    'sub' => 'test65498',
                    'email' => 'test+googlenew@example.com',
                    'email_verified' => true,
                ]),
            ])),
            new MockResponse((string) json_encode([
                'sub' => 'test65498',
                'email' => 'test+googlenew@example.com',
                'given_name' => 'Bob1',
                'family_name' => 'Loblaw1',
                'email_verified' => true,
            ])),
        ]);

        $strategy = $this->getStrategy();
        $strategy->setHttpClient($client);

        $request = $this->getRequest();

        $user = $strategy->handleAuthorizationCode('code', $request);

        $this->assertEquals('test65498', $user->google_claimed_id);
        $this->assertEquals('test+googlenew@example.com', $user->email);
        $this->assertEquals('Bob1', $user->first_name);
        $this->assertEquals('Loblaw1', $user->last_name);

        $this->assertEquals($user->id(), self::getService('test.user_context')->get()->id());
        $this->assertTrue(self::getService('test.user_context')->get()->isFullySignedIn());
    }

    public function testHandleAuthorizationCodeExistingUser(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                'access_token' => 'accessToken',
                'id_token' => $this->getIdToken([
                    'sub' => 'test',
                    'email' => self::$user->email,
                    'email_verified' => true,
                ]),
            ])),
        ]);

        $strategy = $this->getStrategy();
        $strategy->setHttpClient($client);

        $request = $this->getRequest();

        $user = $strategy->handleAuthorizationCode('code', $request);

        $this->assertEquals('test', $user->google_claimed_id);
        $this->assertEquals(self::$user->email, $user->email);
        $this->assertEquals('Bob1', $user->first_name);
        $this->assertEquals('Loblaw1', $user->last_name);

        $this->assertEquals($user->id(), self::getService('test.user_context')->get()->id());
        $this->assertTrue(self::getService('test.user_context')->get()->isFullySignedIn());
    }

    public function testHandleAuthorizationCodeVerifiedEmailCollision(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                'access_token' => 'accessToken',
                'id_token' => $this->getIdToken([
                    'sub' => 'test2893498234',
                    'email' => self::$user->email,
                    'email_verified' => true,
                ]),
            ])),
        ]);

        $strategy = $this->getStrategy();
        $strategy->setHttpClient($client);

        $request = $this->getRequest();

        $user = $strategy->handleAuthorizationCode('code', $request);

        $this->assertEquals('test2893498234', $user->google_claimed_id);
        $this->assertEquals(self::$user->email, $user->email);
        $this->assertEquals('Bob1', $user->first_name);
        $this->assertEquals('Loblaw1', $user->last_name);

        $this->assertEquals($user->id(), self::getService('test.user_context')->get()->id());
        $this->assertTrue(self::getService('test.user_context')->get()->isFullySignedIn());
    }

    public function testSamlDisabled(): void
    {
        $json = (string) json_encode([
            'access_token' => 'accessToken',
            'id_token' => $this->getIdToken([
                'sub' => 'test2893498234',
                'email' => self::$user->email,
                'email_verified' => true,
            ]),
        ]);
        $client = new MockHttpClient([
            new MockResponse($json),
            new MockResponse($json),
            new MockResponse($json),
            new MockResponse($json),
        ]);

        $strategy = $this->getStrategy();
        $strategy->setHttpClient($client);

        $request = $this->getRequest();

        $settings = new CompanySamlSettings();
        $settings->company = self::$company;
        $settings->domain = 'example.com';
        $settings->cert = 'test';
        $settings->entity_id = 1;
        $settings->sso_url = 'https://example.com';
        $settings->saveOrFail();

        $user = $strategy->handleAuthorizationCode('code', $request);
        $this->assertEquals(self::$user->id, $user->id());

        $settings->enabled = true;
        $settings->saveOrFail();
        $user = $strategy->handleAuthorizationCode('code', $request);
        $this->assertEquals(self::$user->id, $user->id());

        $settings->disable_non_sso = true;
        $settings->saveOrFail();
        try {
            $strategy->handleAuthorizationCode('code', $request);
        } catch (AuthException $e) {
            $this->assertEquals('You do not have access to any companies.', $e->getMessage());
        }

        $settings->enabled = false;
        $settings->saveOrFail();
        $user = $strategy->handleAuthorizationCode('code', $request);
        $this->assertEquals(self::$user->id, $user->id());
    }

    public function testHandleAuthorizationCodeUnverifiedEmailCollision(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Could not find a matching user account');

        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                'access_token' => 'accessToken',
                'id_token' => $this->getIdToken([
                    'sub' => 'test123412341234',
                    'email' => self::$user->email,
                    'email_verified' => false,
                ]),
            ])),
        ]);

        $strategy = $this->getStrategy();
        $strategy->setHttpClient($client);

        $request = $this->getRequest();

        $this->getStrategy()->handleAuthorizationCode('code', $request);
    }

    public function testHandleAuthorizationCodeDisabledUser(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Sorry, your account has been disabled.');

        self::$user->enabled = false;
        self::$user->saveOrFail();

        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                'access_token' => 'accessToken',
                'id_token' => $this->getIdToken([
                    'sub' => 'test',
                    'email' => self::$user->email,
                    'email_verified' => true,
                ]),
            ])),
        ]);

        $strategy = $this->getStrategy();
        $strategy->setHttpClient($client);

        $request = $this->getRequest();

        $this->getStrategy()->handleAuthorizationCode('code', $request);
    }
}
