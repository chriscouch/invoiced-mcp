<?php

namespace App\Integrations\Services;

use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\NetSuite\Models\NetSuiteAccount;

class Netsuite extends AbstractService
{
    private ?NetSuiteAccount $account = null;

    public function isAccountingIntegration(): bool
    {
        return true;
    }

    public function isConnected(): bool
    {
        return $this->account || NetSuiteAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!$account) {
            return null;
        }

        return $account->account_id;
    }

    public function getExtra(): \stdClass
    {
        $syncProfile = $this->getSyncProfile();

        $profile = $syncProfile?->toArray();

        return (object) [
            'sync_profile' => $profile,
        ];
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('NetSuite account is not connected');
        }

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove NetSuite account');
        }
        $this->account = null;
    }

    /**
     * Gets the connected NetSuite account.
     */
    public function getAccount(): ?NetSuiteAccount
    {
        if (!$this->accountLoaded) {
            $this->account = NetSuiteAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }

    /**
     * Gets the sync profile for a company.
     */
    public function getSyncProfile(): ?AccountingSyncProfile
    {
        return AccountingSyncModelFactory::getSyncProfile(IntegrationType::NetSuite, $this->company);
    }
}
