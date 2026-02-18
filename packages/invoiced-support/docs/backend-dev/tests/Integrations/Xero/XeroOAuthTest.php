<?php

namespace App\Tests\Integrations\Xero;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\Xero\Libs\XeroOAuth;
use App\Integrations\Xero\Models\XeroAccount;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class XeroOAuthTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getOAuth(): XeroOAuth
    {
        return self::getService('test.oauth_integration_factory')->get('xero');
    }

    public function testGetRedirectUrl(): void
    {
        $this->assertEquals('https://invoiced.localhost:1234/oauth/xero/connect', $this->getOAuth()->getRedirectUrl());
    }

    public function testGetAuthorizationUrl(): void
    {
        $endpoint = $this->getOAuth()->getAuthorizationUrl('test_state');

        $this->assertStringStartsWith('https://login.xero.com/identity/connect/authorize?', $endpoint);

        /** @var array $parsed */
        $parsed = parse_url($endpoint);

        $expected = [
            'response_type' => 'code',
            'scope' => 'offline_access openid profile email accounting.transactions accounting.settings accounting.contacts',
            'client_id' => 'id_test',
            'state' => 'test_state',
            'redirect_uri' => 'https://invoiced.localhost:1234/oauth/xero/connect',
        ];

        parse_str($parsed['query'], $query);
        $this->assertEquals($expected, $query);
    }

    public function testExchangeAuthCodeForToken(): void
    {
        $accessToken = (object) [
            'access_token' => 'ACCESS_TOKEN',
            'expires_in' => 3600,
            'refresh_token' => 'REFRESH_TOKEN',
        ];

        $client = new MockHttpClient([
            new MockResponse((string) json_encode($accessToken)),
        ]);

        $oauth = $this->getOAuth();
        $oauth->setHttpClient($client);

        $result = $oauth->exchangeAuthCodeForToken('auth_code');

        $this->assertTrue($result->accessTokenExpiration->greaterThan(CarbonImmutable::now()));
        $this->assertEquals(new OAuthAccessToken(
            'ACCESS_TOKEN',
            $result->accessTokenExpiration,
            'REFRESH_TOKEN',
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

        $accessToken = new OAuthAccessToken(
            'ACCESS_TOKEN',
            new CarbonImmutable('2099-01-01'),
            'REFRESH_TOKEN',
            null
        );

        $account = new XeroAccount();

        $oauth->handleAccessToken($accessToken, $account);

        $this->assertEquals('ACCESS_TOKEN', $account->access_token);
        $this->assertEquals(4070937600, $account->expires);
        $this->assertEquals('REFRESH_TOKEN', $account->session_handle);

        $accessToken = new OAuthAccessToken(
            'new_tok',
            new CarbonImmutable('2099-01-01'),
            'new_session_handle',
            null
        );

        $oauth->handleAccessToken($accessToken, $account);

        $this->assertEquals('new_tok', $account->refresh()->access_token);
        $this->assertEquals('new_session_handle', $account->session_handle);
    }
}
