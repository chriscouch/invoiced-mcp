<?php

namespace App\Integrations\BusinessCentral;

use App\Integrations\Enums\IntegrationType;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\Models\OAuthAccount;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BusinessCentralOAuth extends AbstractOAuthIntegration
{
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        HttpClientInterface $httpClient,
        array $settings,
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

        // Redirect the user to a page where they can select the environment and company to use
        return $this->urlGenerator->generate('business_central_select_company', ['companyId' => $account->tenant()->identifier], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function getAccount(): ?OAuthAccount
    {
        return OAuthAccount::where('integration', IntegrationType::BusinessCentral->value)
            ->oneOrNull();
    }

    public function makeAccount(): OAuthAccount
    {
        $account = new OAuthAccount();
        $account->integration = IntegrationType::BusinessCentral;

        return $account;
    }
}
