<?php

namespace App\Tests\Integrations\Stripe;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\Stripe\StripeOAuth;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use stdClass;
use Stripe\StripeClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class StripeOAuthTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getOAuth(): StripeOAuth
    {
        return self::getService('test.oauth_integration_factory')->get('stripe');
    }

    public function testGetRedirectUrl(): void
    {
        $this->assertEquals('http://invoiced.localhost:1234/oauth/stripe/connect', $this->getOAuth()->getRedirectUrl());
    }

    public function testGetAuthorizationUrl(): void
    {
        $endpoint = $this->getOAuth()->getAuthorizationUrl('test_state');

        $this->assertStringStartsWith('https://connect.stripe.com/oauth/authorize?', $endpoint);

        /** @var array $parsed */
        $parsed = parse_url($endpoint);

        $expected = [
            'response_type' => 'code',
            'scope' => 'read_write',
            'stripe_landing' => 'register',
            'client_id' => 'ca_test_shared',
            'always_prompt' => 'true',
            'state' => 'test_state',
            'redirect_uri' => 'http://invoiced.localhost:1234/oauth/stripe/connect',
            'stripe_user' => [
                'email' => 'test@example.com',
                'country' => 'US',
                'business_name' => 'TEST',
                'first_name' => 'Bob',
                'last_name' => 'Loblaw',
                'street_address' => 'Company, Address',
                'city' => 'Austin',
                'state' => 'TX',
                'zip' => '78701',
                'currency' => 'usd',
            ],
        ];

        parse_str($parsed['query'], $query);
        $this->assertEquals($expected, $query);
    }

    public function testExchangeAuthCodeForToken(): void
    {
        $accessToken = new stdClass();
        $accessToken->stripe_user_id = 'usr_test';
        $accessToken->access_token = 'tok_test';
        $accessToken->refresh_token = 'tok_refresh';
        $accessToken->stripe_publishable_key = 'pub_key';

        $client = new MockHttpClient([
            new MockResponse((string) json_encode($accessToken)),
        ]);

        $oauth = $this->getOAuth();
        $oauth->setHttpClient($client);

        $result = $oauth->exchangeAuthCodeForToken('auth_code');

        $this->assertEquals(new OAuthAccessToken(
            'tok_test',
            new CarbonImmutable('2099-01-01'),
            'tok_refresh',
            null
        ), $result);
    }

    public function testExchangeAuthCodeForTokenError(): void
    {
        $this->expectException(OAuthException::class);

        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 400]),
        ]);

        $oauth = $this->getOAuth();
        $oauth->setHttpClient($client);

        $oauth->exchangeAuthCodeForToken('auth_code');
    }

    public function testGetAccount(): void
    {
        $oauth = $this->getOAuth();

        $merchantAccountDefault = new MerchantAccount();
        $merchantAccountDefault->name = 'test';
        $merchantAccountDefault->gateway_id = '0';
        $merchantAccountDefault->gateway = StripeGateway::ID;
        $merchantAccountDefault->credentials = new stdClass();
        $merchantAccountDefault->saveOrFail();

        $result = new stdClass();
        $result->stripe_user_id = 'TEST_UPDATE_USER_ID';
        $result->access_token = 'CONNECT_UPDATE_KEY';
        $result->refresh_token = 'tok_update_efresh';
        $result->stripe_publishable_key = 'pub_upadate_key';
        $oauth->setLastTokenResult($result);

        $merchantAccount = $oauth->getAccount();

        $this->assertInstanceOf(MerchantAccount::class, $merchantAccount);
        $this->assertEquals($merchantAccountDefault->id, $merchantAccount->id);
    }

    public function testHandleAccessToken(): void
    {
        $oauth = $this->getOAuth();

        $staticAccount = Mockery::mock('Stripe\Account');
        $staticAccount->shouldReceive('retrieve')
            ->withArgs([
                'TEST_STRIPE_USER_ID',
            ])
            ->andReturn((object) [
                'settings' => (object) [
                    'dashboard' => (object) [
                        'display_name' => 'Test',
                    ],
                ],
            ]);
        $stripe = Mockery::mock(StripeClient::class);
        $stripe->accounts = $staticAccount;

        $appleDomain = Mockery::mock('Stripe\ApplePayDomain');
        $appleDomain->shouldReceive('create')
            ->withArgs([
                ['domain_name' => self::$company->username.'.invoiced.localhost'],
                ['stripe_account' => 'TEST_STRIPE_USER_ID'],
            ])
            ->andReturn([]);
        $stripe->applePayDomains = $appleDomain;
        $oauth->setStripe($stripe);

        $accessToken = new OAuthAccessToken('CONNECT_KEY', new CarbonImmutable('2099-01-01'), 'tok_refresh', null);
        $result = new stdClass();
        $result->stripe_user_id = 'TEST_STRIPE_USER_ID';
        $result->access_token = 'CONNECT_KEY';
        $result->refresh_token = 'tok_refresh';
        $result->stripe_publishable_key = 'pub_key';
        $merchantAccount = new MerchantAccount();
        $oauth->setLastTokenResult($result);

        $oauth->handleAccessToken($accessToken, $merchantAccount);

        $merchantAccount = MerchantAccount::where('gateway', 'stripe')
            ->where('gateway_id', 'TEST_STRIPE_USER_ID')
            ->oneOrNull();
        $this->assertInstanceOf(MerchantAccount::class, $merchantAccount);
        $this->assertEquals('Test', $merchantAccount->name);
        $expected = [
            'user_id' => 'TEST_STRIPE_USER_ID',
            'key' => 'CONNECT_KEY',
            'publishable_key' => 'pub_key',
            'access_token' => 'CONNECT_KEY',
            'access_token_expiration' => '2099-01-01T00:00:00+00:00',
            'refresh_token' => 'tok_refresh',
            'refresh_token_expiration' => null,
        ];
        $this->assertEquals((object) $expected, $merchantAccount->credentials);

        $paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $this->assertTrue($paymentMethod->enabled);
        $this->assertEquals('stripe', $paymentMethod->gateway);
        $this->assertEquals($merchantAccount->id(), $paymentMethod->merchant_account);

        // running it again should update the existing account
        $accessToken = new OAuthAccessToken('CONNECT_KEY', new CarbonImmutable('2099-01-01'), 'tok_refresh2', null);

        $oauth->handleAccessToken($accessToken, $merchantAccount);

        $expected = [
            'user_id' => 'TEST_STRIPE_USER_ID',
            'key' => 'CONNECT_KEY',
            'publishable_key' => 'pub_key',
            'access_token' => 'CONNECT_KEY',
            'access_token_expiration' => '2099-01-01T00:00:00+00:00',
            'refresh_token' => 'tok_refresh2',
            'refresh_token_expiration' => null,
        ];
        $this->assertEquals((object) $expected, $merchantAccount->refresh()->credentials);
    }
}
