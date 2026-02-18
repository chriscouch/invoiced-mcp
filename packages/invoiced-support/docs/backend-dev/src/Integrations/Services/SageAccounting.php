<?php

namespace App\Integrations\Services;

use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;

class SageAccounting extends AbstractOAuthService
{
    public function isAccountingIntegration(): bool
    {
        return true;
    }

    public function getExtra(): \stdClass
    {
        $syncProfile = $this->getSyncProfile();

        return (object) [
            'sync_profile' => $syncProfile?->toArray(),
        ];
    }

    /**
     * Gets the sync profile for a company.
     */
    public function getSyncProfile(): ?AccountingSyncProfile
    {
        return AccountingSyncModelFactory::getSyncProfile(IntegrationType::SageAccounting, $this->company);
    }
}
