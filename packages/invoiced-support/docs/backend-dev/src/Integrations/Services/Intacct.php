<?php

namespace App\Integrations\Services;

use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Intacct\Models\IntacctAccount;

class Intacct extends AbstractService
{
    private ?IntacctAccount $account = null;

    public function isAccountingIntegration(): bool
    {
        return true;
    }

    public function isConnected(): bool
    {
        return $this->account || IntacctAccount::queryWithTenant($this->company)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        $account = $this->getAccount();
        if (!$account) {
            return null;
        }

        if ($entityId = $account->entity_id) {
            return $account->intacct_company_id.' | '.$entityId;
        }

        return $account->intacct_company_id;
    }

    public function getExtra(): \stdClass
    {
        $syncProfile = $this->getSyncProfile();
        $account = $this->getAccount();

        return (object) [
            'sync_profile' => $syncProfile ? $syncProfile->toArray() : null,
            'sync_all_entities' => $account ? $account->sync_all_entities : null,
        ];
    }

    public function disconnect(): void
    {
        $account = $this->getAccount();
        if (!$account) {
            throw new IntegrationException('Intacct account is not connected');
        }

        if (!$account->delete()) {
            throw new IntegrationException('Could not remove Intacct account');
        }
        $this->account = null;
    }

    /**
     * Gets the connected Intacct account.
     */
    public function getAccount(): ?IntacctAccount
    {
        if (!$this->accountLoaded) {
            $this->account = IntacctAccount::queryWithTenant($this->company)->oneOrNull();
            $this->accountLoaded = true;
        }

        return $this->account;
    }

    /**
     * Gets the sync profile for a company.
     */
    public function getSyncProfile(): ?AccountingSyncProfile
    {
        return AccountingSyncModelFactory::getSyncProfile(IntegrationType::Intacct, $this->company);
    }
}
