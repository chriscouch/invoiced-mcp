<?php

namespace App\Integrations\FreshBooks;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\Models\OAuthAccount;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FreshBooksOAuth extends AbstractOAuthIntegration
{
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        array $settings,
        private FreshBooksApi $api,
    ) {
        parent::__construct($urlGenerator, $httpClient, $settings);
    }

    public function getRedirectUrl(): string
    {
        // HTTPS is required of redirect URLs
        return str_replace('http://', 'https://', parent::getRedirectUrl());
    }

    public function getSuccessRedirectUrl(): string
    {
        /** @var OAuthAccount $account */
        $account = $this->getAccount();

        // Determine which business to use
        if (!$account->getMetadata('business')) {
            $userProfile = $this->api->getUserProfile($account);
            // TODO: need to add a selector when there is more than one business
            $account->addMetadata('business', $userProfile->business_memberships[0]->business->id);
            $account->addMetadata('account', $userProfile->business_memberships[0]->business->account_id);
            $account->saveOrFail();
        }

        return parent::getSuccessRedirectUrl();
    }

    public function getAccount(): ?OAuthAccount
    {
        return OAuthAccount::where('integration', IntegrationType::FreshBooks->value)
            ->oneOrNull();
    }

    public function makeAccount(): OAuthAccount
    {
        $account = new OAuthAccount();
        $account->integration = IntegrationType::FreshBooks;

        return $account;
    }
}
