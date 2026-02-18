<?php

namespace App\EntryPoint\CronJob;

use App\Integrations\AccountingSync\AbstractSyncCronJob;
use App\Integrations\Enums\IntegrationType;

/**
 * Enqueues BusinessCentralSyncJob for all tenants with
 * an active business central integration.
 */
class BusinessCentralSync extends AbstractSyncCronJob
{
    public function getTasks(): iterable
    {
        return $this->getAccountingSyncProfiles(IntegrationType::BusinessCentral);
    }
}
