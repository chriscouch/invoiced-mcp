<?php

namespace App\Tests\Integrations\SageAccounting;

use App\Tests\Integrations\OAuth\AbstractOAuthTest;

class SageAccountingOAuthTest extends AbstractOAuthTest
{
    protected function getServiceKey(): string
    {
        return 'sage_accounting';
    }

    protected function getExpectedRedirectUrl(): string
    {
        return 'https://invoiced.localhost:1234/oauth/sage_accounting/connect';
    }

    protected function getExpectedAuthorizationUrl(): string
    {
        return 'https://www.sageone.com/oauth2/auth/central';
    }

    protected function getExpectedAuthorizationUrlQuery(): array
    {
        return [
            'response_type' => 'code',
            'scope' => 'full_access',
            'client_id' => 'id_test',
            'state' => 'test_state',
            'redirect_uri' => 'https://invoiced.localhost:1234/oauth/sage_accounting/connect',
        ];
    }
}
