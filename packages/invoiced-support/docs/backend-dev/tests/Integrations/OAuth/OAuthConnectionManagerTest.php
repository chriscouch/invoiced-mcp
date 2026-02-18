<?php

namespace App\Tests\Integrations\OAuth;

use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\Interfaces\OAuthIntegrationInterface;
use App\Integrations\OAuth\OAuthConnectionManager;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class OAuthConnectionManagerTest extends AppTestCase
{
    private function get(): OAuthConnectionManager
    {
        return self::getService('test.oauth_connection_manager');
    }

    public function testRefresh(): void
    {
        $manager = $this->get();
        $account = Mockery::mock(OAuthAccountInterface::class);
        $account->shouldReceive('persistOAuth')->once();
        $account->shouldReceive('getToken')
            ->andReturn(new OAuthAccessToken('access', CarbonImmutable::now(), 'refresh', null));
        $oauth = Mockery::mock(OAuthIntegrationInterface::class);
        $oauth->shouldReceive('refresh')
            ->withArgs([$account])
            ->once();

        $manager->refresh($oauth, $account);
    }
}
