<?php

namespace App\EntryPoint\CronJob;

use App\Integrations\AccountingSync\AbstractSyncCronJob;
use App\Integrations\Enums\IntegrationType;

/**
 * Enqueues a sync job for all tenants with
 * an active FreshBooks integration.
 */
class FreshbooksSync extends AbstractSyncCronJob
{
    public function getTasks(): iterable
    {
        return $this->getAccountingSyncProfiles(IntegrationType::FreshBooks);
    }
}
