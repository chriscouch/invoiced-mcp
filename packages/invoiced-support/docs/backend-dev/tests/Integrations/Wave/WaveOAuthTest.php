<?php

namespace App\Tests\Integrations\Wave;

use App\Tests\Integrations\OAuth\AbstractOAuthTest;

class WaveOAuthTest extends AbstractOAuthTest
{
    protected function getServiceKey(): string
    {
        return 'wave';
    }

    protected function getExpectedRedirectUrl(): string
    {
        return 'https://invoiced.localhost:1234/oauth/wave/connect';
    }

    protected function getExpectedAuthorizationUrl(): string
    {
        return 'https://api.waveapps.com/oauth2/authorize/';
    }

    protected function getExpectedAuthorizationUrlQuery(): array
    {
        return [
            'response_type' => 'code',
            'scope' => 'business:read customer:read invoice:* product:read vendor:*',
            'client_id' => 'id_test',
            'state' => 'test_state',
            'redirect_uri' => 'https://invoiced.localhost:1234/oauth/wave/connect',
        ];
    }
}
