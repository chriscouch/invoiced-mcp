<?php

namespace App\EntryPoint\CronJob;

use App\Integrations\AccountingSync\AbstractSyncCronJob;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;

/**
 * Enqueues XeroSyncJob for all tenants with
 * an active Xero integration.
 */
class XeroSync extends AbstractSyncCronJob
{
    public function getTasks(): iterable
    {
        return XeroSyncProfile::queryWithoutMultitenancyUnsafe()
            ->join(XeroAccount::class, 'tenant_id', 'tenant_id')
            ->all();
    }
}
