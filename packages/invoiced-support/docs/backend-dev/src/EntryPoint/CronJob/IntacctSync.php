<?php

namespace App\EntryPoint\CronJob;

use App\Integrations\AccountingSync\AbstractSyncCronJob;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;

/**
 * Enqueues IntacctSyncJob for all tenants with
 * an active Xero integration.
 */
class IntacctSync extends AbstractSyncCronJob
{
    public function getTasks(): iterable
    {
        return IntacctSyncProfile::queryWithoutMultitenancyUnsafe()
            ->join(IntacctAccount::class, 'tenant_id', 'tenant_id')
            ->all();
    }
}
