<?php

namespace App\Tests\Integrations\FreshBooks;

use App\Tests\Integrations\OAuth\AbstractOAuthTest;

class FreshBooksOAuthTest extends AbstractOAuthTest
{
    protected function getServiceKey(): string
    {
        return 'freshbooks';
    }

    protected function getExpectedRedirectUrl(): string
    {
        return 'https://invoiced.localhost:1234/oauth/freshbooks/connect';
    }

    protected function getExpectedAuthorizationUrl(): string
    {
        return 'https://my.freshbooks.com/service/auth/oauth/authorize';
    }

    protected function getExpectedAuthorizationUrlQuery(): array
    {
        return [
            'response_type' => 'code',
            'scope' => '',
            'client_id' => 'id_test',
            'state' => 'test_state',
            'redirect_uri' => 'https://invoiced.localhost:1234/oauth/freshbooks/connect',
        ];
    }
}
