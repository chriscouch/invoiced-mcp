<?php

namespace App\EntryPoint\CronJob;

use App\Integrations\AccountingSync\AbstractSyncCronJob;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;

/**
 * Enqueues QuickBooksSyncJob for all tenants with
 * an active QuickBooks Online integration.
 */
class QuickBooksOnlineSync extends AbstractSyncCronJob
{
    public static function getName(): string
    {
        return 'quickbooks_online_sync';
    }

    public function getTasks(): iterable
    {
        return QuickBooksOnlineSyncProfile::queryWithoutMultitenancyUnsafe()
            ->join(QuickBooksAccount::class, 'tenant_id', 'tenant_id')
            ->all();
    }
}
