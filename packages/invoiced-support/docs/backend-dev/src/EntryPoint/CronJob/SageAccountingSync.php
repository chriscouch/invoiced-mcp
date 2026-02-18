<?php

namespace App\EntryPoint\CronJob;

use App\Integrations\AccountingSync\AbstractSyncCronJob;
use App\Integrations\Enums\IntegrationType;

/**
 * Enqueues a sync job for all tenants with
 * an active Sage Accounting integration.
 */
class SageAccountingSync extends AbstractSyncCronJob
{
    public function getTasks(): iterable
    {
        return $this->getAccountingSyncProfiles(IntegrationType::SageAccounting);
    }
}
