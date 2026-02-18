<?php

namespace App\Integrations\Slack;

use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\AbstractOAuthIntegration;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\OAuthAccessToken;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

class SlackOAuth extends AbstractOAuthIntegration
{
    public function getRedirectUrl(): string
    {
        $url = parent::getRedirectUrl();

        // Slack does not like our localhost URL so we must supply a dummy URL
        if (str_contains($url, 'localhost')) {
            return 'https://invoiced.com/oauth/slack/connect';
        }

        return $url;
    }

    protected function makeAccessToken(stdClass $token): OAuthAccessToken
    {
        if (isset($token->error)) {
            throw new OAuthException('Could not create access token: '.$token->error);
        }

        return parent::makeAccessToken($token);
    }

    /**
     * @param SlackAccount $account
     */
    protected function customAccountSetup(OAuthAccountInterface $account, ?Request $request): void
    {
        $account->name = $this->lastTokenResult->team->name;
        $account->team_id = $this->lastTokenResult->team->id;
    }

    public function getAccount(): ?OAuthAccountInterface
    {
        return SlackAccount::oneOrNull();
    }

    public function makeAccount(): OAuthAccountInterface
    {
        return new SlackAccount();
    }
}
