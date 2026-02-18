<?php

namespace App\Integrations\Services;

use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Xero\Models\XeroAccount;

class Xero extends AbstractService
{
    private ?XeroAccount $account = null;

    public function isAccountingIntegration(): bool
    {
        return true;
    }

    public function isConnected(): bool
    {
        return $this->account || XeroAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!($account instanceof XeroAccount)) {
            return null;
        }

        return $account->name;
    }

    public function getExtra(): \stdClass
    {
        $syncProfile = $this->getSyncProfile();
        $result = [
            'sync_profile' => $syncProfile ? $syncProfile->toArray() : null,
        ];

        return (object) $result;
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('Xero account is not connected');
        }

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove Xero account');
        }
        $this->account = null;
    }

    /**
     * Gets the connected Xero account.
     */
    public function getAccount(): ?XeroAccount
    {
        if (!$this->accountLoaded) {
            $this->account = XeroAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }

    /**
     * Gets the sync profile for a company.
     */
    public function getSyncProfile(): ?AccountingSyncProfile
    {
        return AccountingSyncModelFactory::getSyncProfile(IntegrationType::Xero, $this->company);
    }
}
