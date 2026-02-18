<?php

namespace App\Integrations\Services;

use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\OAuth\Models\OAuthAccount;

abstract class AbstractOAuthService extends AbstractService
{
    protected ?OAuthAccount $account = null;

    public function isConnected(): bool
    {
        return $this->account || OAuthAccount::queryWithTenant($this->company)->where('integration', $this->integrationType->value)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!($account instanceof OAuthAccount)) {
            return null;
        }

        return $account->name;
    }

    public function getExtra(): \stdClass
    {
        return (object) [];
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('Account is not connected');
        }

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove account');
        }
        $this->account = null;
    }

    /**
     * Gets the connected account.
     */
    public function getAccount(): ?OAuthAccount
    {
        if (!$this->accountLoaded) {
            $this->account = OAuthAccount::queryWithTenant($this->company)
                ->where('integration', $this->integrationType->value)
                ->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }
}
