<?php

namespace App\Tests\Integrations\Slack;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\Slack\SlackAccount;
use App\Integrations\Slack\SlackOAuth;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use stdClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SlackOAuthTest extends AppTestCase
{
    private function getAccessToken(): stdClass
    {
        $team = new stdClass();
        $team->id = 'usr_test';
        $team->name = 'Test';

        $accessToken = new stdClass();
        $accessToken->team = $team;
        $accessToken->access_token = 'ACCESS_TOKEN';
        $accessToken->incoming_webhook = new stdClass();
        $accessToken->incoming_webhook->url = 'http://example.com/messages';
        $accessToken->incoming_webhook->configuration_url = 'http://example.com/settings';
        $accessToken->incoming_webhook->channel = '#test';

        return $accessToken;
    }

    private function getOAuth(): SlackOAuth
    {
        return self::getService('test.oauth_integration_factory')->get('slack');
    }

    public function testGetRedirectUrl(): void
    {
        $this->assertEquals('https://invoiced.com/oauth/slack/connect', $this->getOAuth()->getRedirectUrl());
    }

    public function testGetAuthorizationUrl(): void
    {
        $endpoint = $this->getOAuth()->getAuthorizationUrl('test_state');

        $this->assertStringStartsWith('https://slack.com/oauth/v2/authorize?', $endpoint);

        /** @var array $parsed */
        $parsed = parse_url($endpoint);

        $expected = [
            'response_type' => 'code',
            'scope' => 'channels:join,channels:read,chat:write,groups:read,im:read,mpim:read',
            'client_id' => 'id_test',
            'state' => 'test_state',
            'redirect_uri' => 'https://invoiced.com/oauth/slack/connect',
        ];

        parse_str($parsed['query'], $query);
        $this->assertEquals($expected, $query);
    }

    public function testExchangeAuthCodeForToken(): void
    {
        $accessToken = $this->getAccessToken();

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
        $oauth = Mockery::mock(SlackOAuth::class)->makePartial();

        $oauth->setLastTokenResult($this->getAccessToken());
        $accessToken = new OAuthAccessToken(
            'ACCESS_TOKEN',
            new CarbonImmutable('2099-01-01'),
            '',
            null
        );

        $slackAccount = new SlackAccount();

        $oauth->handleAccessToken($accessToken, $slackAccount);

        $this->assertEquals('ACCESS_TOKEN', $slackAccount->access_token);
        $this->assertEquals('Test', $slackAccount->name);
        $this->assertEquals('usr_test', $slackAccount->team_id);
        $this->assertEquals(null, $slackAccount->webhook_url);
        $this->assertEquals(null, $slackAccount->webhook_channel);
        $this->assertEquals(null, $slackAccount->webhook_config_url);

        // running it again should update the existing account
        $accessToken = new OAuthAccessToken(
            'ACCESS_TOKEN_2',
            new CarbonImmutable('2099-01-01'),
            '',
            null
        );

        $oauth->handleAccessToken($accessToken, $slackAccount);

        $this->assertEquals('ACCESS_TOKEN_2', $slackAccount->access_token);
    }
}
