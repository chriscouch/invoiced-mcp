<?php

namespace App\Integrations\Services;

use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Interfaces\IntegrationInterface;
use stdClass;

class QuickbooksDesktop extends AbstractService implements IntegrationInterface
{
    private ?AccountingSyncProfile $syncProfile = null;

    public function isAccountingIntegration(): bool
    {
        return true;
    }

    public function isConnected(): bool
    {
        return $this->syncProfile || AccountingSyncProfile::where('integration', IntegrationType::QuickBooksDesktop->value)->count() > 0;
    }

    public function getConnectedName(): ?string
    {
        return null;
    }

    public function getExtra(): stdClass
    {
        $syncProfile = $this->getSyncProfile();

        $result = null;
        if ($syncProfile) {
            $result = $syncProfile->toArray();
            if ($date = $syncProfile->invoice_start_date) {
                $result['invoice_start_date'] = date('Y-m-d', $date);
            }

            // Add extra parameters for use by the quickbooks-desktop project
            $result['debug'] = $syncProfile->tenant()->features->has('log_quickbooks_desktop');
            $result['time_zone'] = $syncProfile->tenant()->time_zone;
            $parameters = $syncProfile->parameters;
            $result['parent_child_enabled'] = $parameters->parent_child_enabled ?? false;
            $result['all_invoices'] = $parameters->all_invoices ?? true;
        }

        return (object) [
            'sync_profile' => $result,
        ];
    }

    public function disconnect(): void
    {
        if ($syncProfile = $this->getSyncProfile()) {
            $syncProfile->delete();
            $this->syncProfile = null;
        }
    }

    public function getSyncProfile(): ?AccountingSyncProfile
    {
        if (!$this->accountLoaded) {
            $this->syncProfile = AccountingSyncModelFactory::getSyncProfile(IntegrationType::QuickBooksDesktop, $this->company);
            $this->accountLoaded = true;
        }

        return $this->syncProfile;
    }
}
