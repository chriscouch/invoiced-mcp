<?php

namespace App\Tests\Integrations\GoCardless;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\GoCardless\GoCardlessOAuth;
use App\Integrations\OAuth\OAuthAccessToken;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use stdClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GoCardlessOAuthTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getOAuth(): GoCardlessOAuth
    {
        return self::getService('test.oauth_integration_factory')->get('gocardless');
    }

    public function testGetAuthorizationUrl(): void
    {
        $endpoint = $this->getOAuth()->getAuthorizationUrl('test_state');

        $this->assertStringStartsWith('https://connect-sandbox.gocardless.com/oauth/authorize?', $endpoint);

        /** @var array $parsed */
        $parsed = parse_url($endpoint);

        $expected = [
            'response_type' => 'code',
            'scope' => 'read_write',
            'initial_view' => 'signup',
            'client_id' => 'gc_client_id',
            'redirect_uri' => 'http://invoiced.localhost:1234/oauth/gocardless/connect',
            'state' => 'test_state',
            'language' => 'en',
            'prefill' => [
                'given_name' => 'Bob',
                'family_name' => 'Loblaw',
                'organisation_name' => 'TEST',
                'email' => 'test@example.com',
            ],
        ];

        parse_str($parsed['query'], $query);
        $this->assertEquals($expected, $query);
    }

    public function testExchangeAuthCodeForToken(): void
    {
        $accessToken = new stdClass();
        $accessToken->organisation_id = '1234';
        $accessToken->email = 'test@example.com';
        $accessToken->access_token = 'a secret';

        $client = new MockHttpClient([
            new MockResponse((string) json_encode($accessToken)),
        ]);

        $oauth = $this->getOAuth();
        $oauth->setHttpClient($client);

        $result = $oauth->exchangeAuthCodeForToken('auth_code');

        $this->assertEquals(new OAuthAccessToken(
            'a secret',
            new CarbonImmutable('2099-01-01'),
            '',
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

    public function testHandleAccessToken(): void
    {
        $oauth = $this->getOAuth();
        $accessToken = new OAuthAccessToken('a secret', new CarbonImmutable('2099-01-01'), '', null);

        $merchantAccount = new MerchantAccount();

        $oauth->handleAccessToken($accessToken, $merchantAccount);

        // should create a merchant account
        $this->assertInstanceOf(MerchantAccount::class, $merchantAccount);
        $this->assertEquals('test@example.com', $merchantAccount->name);
        $this->assertEquals('gocardless', $merchantAccount->gateway);
        $this->assertEquals('1234', $merchantAccount->gateway_id);
        $expected = [
            'organisation_id' => '1234',
            'access_token' => 'a secret',
            'environment' => 'sandbox',
            'access_token_expiration' => '2099-01-01T00:00:00+00:00',
            'refresh_token' => '',
            'refresh_token_expiration' => null,
        ];
        $this->assertEquals((object) $expected, $merchantAccount->credentials);

        // should enable the payment method
        $paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::DIRECT_DEBIT);
        $this->assertTrue($paymentMethod->enabled);
        $this->assertEquals('gocardless', $paymentMethod->gateway);
        $this->assertEquals($merchantAccount->id(), $paymentMethod->merchant_account);

        // test the same for UK
        self::$company->country = 'UK';

        $oauth->handleAccessToken($accessToken, $merchantAccount);

        $paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::DIRECT_DEBIT);
        $this->assertTrue($paymentMethod->enabled);
        $this->assertEquals('gocardless', $paymentMethod->gateway);
        $this->assertEquals($merchantAccount->id(), $paymentMethod->merchant_account);

        // running it again should update the existing account
        $accessToken = new OAuthAccessToken('even more secret', new CarbonImmutable('2099-01-01'), '', null);

        $oauth->handleAccessToken($accessToken, $merchantAccount);

        $expected = [
            'organisation_id' => '1234',
            'access_token' => 'even more secret',
            'environment' => 'sandbox',
            'access_token_expiration' => '2099-01-01T00:00:00+00:00',
            'refresh_token' => '',
            'refresh_token_expiration' => null,
        ];
        $this->assertEquals((object) $expected, $merchantAccount->credentials);
    }

    public function testGetSuccessUrl(): void
    {
        $oauth = $this->getOAuth();
        $this->assertEquals('http://app.invoiced.localhost:1236/settings/payments', $oauth->getSuccessRedirectUrl());
    }
}
