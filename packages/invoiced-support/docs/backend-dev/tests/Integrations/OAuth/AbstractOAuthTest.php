<?php

namespace App\Tests\Integrations\OAuth;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

abstract class AbstractOAuthTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    abstract protected function getServiceKey(): string;

    abstract protected function getExpectedRedirectUrl(): string;

    abstract protected function getExpectedAuthorizationUrl(): string;

    abstract protected function getExpectedAuthorizationUrlQuery(): array;

    protected function getOAuth(): AbstractOAuthIntegration
    {
        return self::getService('test.oauth_integration_factory')->get($this->getServiceKey());
    }

    public function testGetRedirectUrl(): void
    {
        $this->assertEquals($this->getExpectedRedirectUrl(), $this->getOAuth()->getRedirectUrl());
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->getOAuth()->getAuthorizationUrl('test_state');
        $this->assertStringStartsWith($this->getExpectedAuthorizationUrl().'?', $url);

        /** @var array $parsed */
        $parsed = parse_url($url);
        parse_str($parsed['query'], $query);
        $this->assertEquals($this->getExpectedAuthorizationUrlQuery(), $query);
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

        $account = $oauth->makeAccount();

        $oauth->handleAccessToken($accessToken, $account);

        $token = $account->getToken();
        $this->assertEquals('ACCESS_TOKEN', $token->accessToken);
        $this->assertEquals(4070908800, $token->accessTokenExpiration->getTimestamp());
        $this->assertEquals('REFRESH_TOKEN', $token->refreshToken);
        $this->assertNull($token->refreshTokenExpiration);

        $accessToken = new OAuthAccessToken(
            'new_tok',
            new CarbonImmutable('2099-01-01'),
            'new_refresh_token',
            null
        );

        $oauth->handleAccessToken($accessToken, $account);

        $token = $account->getToken();
        $this->assertEquals('new_tok', $token->accessToken);
        $this->assertEquals('new_refresh_token', $token->refreshToken);
    }
}
