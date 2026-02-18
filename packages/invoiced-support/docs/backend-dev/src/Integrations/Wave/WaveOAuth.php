<?php

namespace App\Integrations\Wave;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\Models\OAuthAccount;

class WaveOAuth extends AbstractOAuthIntegration
{
    public function getRedirectUrl(): string
    {
        // HTTPS is required of redirect URLs
        return str_replace('http://', 'https://', parent::getRedirectUrl());
    }

    public function getAccount(): ?OAuthAccount
    {
        return OAuthAccount::where('integration', IntegrationType::Wave->value)
            ->oneOrNull();
    }

    public function makeAccount(): OAuthAccount
    {
        $account = new OAuthAccount();
        $account->integration = IntegrationType::Wave;

        return $account;
    }
}
