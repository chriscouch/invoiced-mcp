<?php

namespace App\Tests\Integrations\LawPay;

use App\Integrations\LawPay\LawPayOAuth;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthAccessToken;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use stdClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class LawPayOAuthTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getOAuth(): LawPayOAuth
    {
        $oauth = self::getService('test.oauth_integration_factory')->get('lawpay');

        return $oauth;
    }

    private function getAccessToken(): OAuthAccessToken
    {
        return new OAuthAccessToken('ACCESS_TOKEN', new CarbonImmutable('2099-01-01'), '', null);
    }

    private function getAccessTokenResult(): object
    {
        $accessToken = new stdClass();
        $accessToken->access_token = 'ACCESS_TOKEN';

        return $accessToken;
    }

    public function testGetRedirectUrl(): void
    {
        $oauth = $this->getOAuth();
        $this->assertEquals('http://invoiced.localhost:1234/oauth/lawpay/connect', $oauth->getRedirectUrl());
    }

    public function testGetAuthorizationUrl(): void
    {
        $oauth = $this->getOAuth();
        $endpoint = $oauth->getAuthorizationUrl('test_state');

        $this->assertStringStartsWith('https://secure.lawpay.com/oauth/authorize?', $endpoint);

        /** @var array $parsed */
        $parsed = parse_url($endpoint);

        $expected = [
            'response_type' => 'code',
            'scope' => 'chargeio',
            'client_id' => 'ap_client_id',
            'redirect_uri' => 'http://invoiced.localhost:1234/oauth/lawpay/connect',
            'state' => 'test_state',
        ];

        parse_str($parsed['query'], $query);
        $this->assertEquals($expected, $query);
    }

    public function testExchangeAuthCodeForToken(): void
    {
        $accessToken = $this->getAccessTokenResult();

        $client = new MockHttpClient([
            new MockResponse((string) json_encode($accessToken)),
        ]);

        $oauth = $this->getOAuth();
        $oauth->setHttpClient($client);

        $result = $oauth->exchangeAuthCodeForToken('auth_code');

        $this->assertEquals(new OAuthAccessToken(
            'ACCESS_TOKEN',
            new CarbonImmutable('2099-01-01'),
            '',
            null
        ), $result);
    }

    public function testHandleAccessTokenNoAccounts(): void
    {
        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('We could not connect your LawPay gateway because there are no test LawPay accounts for us to pull in. Please contact support@invoiced.com for help.');

        $credentials = (object) [
            'live_accounts' => [],
            'test_accounts' => [],
        ];

        $client = new MockHttpClient([
            new MockResponse((string) json_encode($credentials)),
        ]);

        $oauth = $this->getOAuth();
        $oauth->setHttpClient($client);

        $merchantAccount = new MerchantAccount();

        $accessToken = $this->getAccessToken();
        $oauth->handleAccessToken($accessToken, $merchantAccount);
    }

    public function testHandleAccessToken(): void
    {
        $credentials = (object) [
            'live_accounts' => [
                (object) [
                    'id' => 'acct_live_operating',
                    'name' => 'Operating',
                    'secret_key' => 'super secret',
                ],
                (object) [
                    'id' => 'acct_live_trust',
                    'name' => 'Trust',
                    'secret_key' => 'super secret',
                ],
            ],
            'test_accounts' => [
                (object) [
                    'id' => 'acct_test_trust',
                    'name' => 'Test Trust',
                    'secret_key' => 'a secret',
                ],
                (object) [
                    'id' => 'acct_test_operating',
                    'name' => 'Test Operating',
                    'secret_key' => 'a secret',
                ],
            ],
        ];

        $client = new MockHttpClient([
            new MockResponse((string) json_encode($credentials)),
        ]);

        $oauth = $this->getOAuth();
        $oauth->setHttpClient($client);

        $merchantAccount = new MerchantAccount();

        $accessToken = $this->getAccessToken();
        $oauth->handleAccessToken($accessToken, $merchantAccount);

        // should create merchant accounts
        $merchantAccountOperating = MerchantAccount::where('gateway', 'lawpay')
            ->where('gateway_id', 'acct_test_operating')
            ->oneOrNull();
        $this->assertInstanceOf(MerchantAccount::class, $merchantAccountOperating);
        $this->assertEquals('Test Operating', $merchantAccountOperating->name);
        $expected = [
            'account_id' => 'acct_test_operating',
            'secret_key' => 'a secret',
        ];
        $this->assertEquals((object) $expected, $merchantAccountOperating->credentials);

        $merchantAccountTrust = MerchantAccount::where('gateway', 'lawpay')
            ->where('gateway_id', 'acct_test_trust')
            ->oneOrNull();
        $this->assertInstanceOf(MerchantAccount::class, $merchantAccountTrust);
        $this->assertEquals('Test Trust', $merchantAccountTrust->name);
        $expected = [
            'account_id' => 'acct_test_trust',
            'secret_key' => 'a secret',
        ];
        $this->assertEquals((object) $expected, $merchantAccountTrust->credentials);

        // should enable the payment method
        $paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $this->assertTrue($paymentMethod->enabled);
        $this->assertEquals('lawpay', $paymentMethod->gateway);
        $this->assertEquals($merchantAccountOperating->id(), $paymentMethod->merchant_account);

        // running it again should update the existing account
        $credentials->test_accounts[0]->secret_key = 'tok_2';
        $client = new MockHttpClient([
            new MockResponse((string) json_encode($credentials)),
        ]);
        $oauth->setHttpClient($client);

        $oauth->handleAccessToken($accessToken, $merchantAccount);

        $expected = [
            'account_id' => 'acct_test_trust',
            'secret_key' => 'tok_2',
        ];
        $this->assertEquals((object) $expected, $merchantAccountTrust->refresh()->credentials);

        $credentials = (object) [
            'live_accounts' => [
                (object) [
                    'id' => 'tok_live_update',
                    'name' => 'Operating',
                    'secret_key' => 'super secret',
                ],
            ],
            'test_accounts' => [
                (object) [
                    'id' => 'tok_test_update',
                    'name' => 'Test Trust',
                    'secret_key' => 'a secret',
                ],
            ],
        ];
        $client = new MockHttpClient([
            new MockResponse((string) json_encode($credentials)),
        ]);
        $oauth->setHttpClient($client);

        $accountsCount = MerchantAccount::count();
        $merchantAccount = new MerchantAccount();
        $merchantAccount->name = 'test';
        $merchantAccount->top_up_threshold_num_of_days = 14;
        $merchantAccount->gateway_id = '0';
        $merchantAccount->gateway = 'lawpay';
        $merchantAccount->credentials = new stdClass();
        $merchantAccount->saveOrFail();

        $oauth->handleAccessToken($accessToken, $merchantAccount);

        $this->assertCount($accountsCount + 1, MerchantAccount::all());
        $this->assertEquals('tok_test_update', $merchantAccount->refresh()->gateway_id);
    }

    public function testSelectMerchantAccount(): void
    {
        $oauth = $this->getOAuth();

        $accounts = [];

        $this->assertNull($oauth->selectMerchantAccount($accounts, PaymentMethod::CREDIT_CARD));

        $trust = new MerchantAccount(['id' => 1]);
        $trust->name = 'Trust account';
        $accounts[] = $trust;
        $this->assertEquals(1, $oauth->selectMerchantAccount($accounts, PaymentMethod::CREDIT_CARD));

        $operating = new MerchantAccount(['id' => 2]);
        $operating->name = 'Operating account';
        $accounts[] = $operating;
        $this->assertEquals(2, $oauth->selectMerchantAccount($accounts, PaymentMethod::CREDIT_CARD));

        $this->assertEquals(2, $oauth->selectMerchantAccount($accounts, PaymentMethod::ACH));

        $ach = new MerchantAccount(['id' => 3]);
        $ach->name = 'ACH account';
        $accounts[] = $ach;
        $this->assertEquals(3, $oauth->selectMerchantAccount($accounts, PaymentMethod::ACH));
    }
}
