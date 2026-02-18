<?php

namespace App\Tests\Integrations\BusinessCentral;

use App\Tests\Integrations\OAuth\AbstractOAuthTest;

class BusinessCentralOAuthTest extends AbstractOAuthTest
{
    protected function getServiceKey(): string
    {
        return 'business_central';
    }

    protected function getExpectedRedirectUrl(): string
    {
        return 'https://invoiced.localhost:1234/oauth/business_central/connect';
    }

    protected function getExpectedAuthorizationUrl(): string
    {
        return 'https://login.microsoftonline.com/organizations/oauth2/v2.0/authorize';
    }

    protected function getExpectedAuthorizationUrlQuery(): array
    {
        return [
            'response_type' => 'code',
            'scope' => 'offline_access https://api.businesscentral.dynamics.com/Financials.ReadWrite.All',
            'client_id' => 'id_test',
            'state' => 'test_state',
            'redirect_uri' => 'https://invoiced.localhost:1234/oauth/business_central/connect',
        ];
    }
}
