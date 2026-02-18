<?php

namespace App\Integrations\AccountingSync;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\CronJob\AbstractTaskQueueCronJob;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ReadSync\ReadSyncJobClassFactory;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\OAuth\Models\OAuthAccount;

abstract class AbstractSyncCronJob extends AbstractTaskQueueCronJob
{
    public function __construct(protected Queue $queue)
    {
    }

    public static function getLockTtl(): int
    {
        return 900;
    }

    /**
     * @param AccountingSyncProfile $task
     */
    public function runTask(mixed $task): bool
    {
        // If the company subscription is active or does not have the accounting_sync feature enabled
        // then don't bother queuing the job.
        $company = $task->tenant();
        if (!$company->billingStatus()->isActive() || !$company->features->has('accounting_sync')) {
            return false;
        }

        $this->enqueue($task);

        return true;
    }

    protected function enqueue(AccountingSyncProfile $syncProfile): void
    {
        $jobClass = ReadSyncJobClassFactory::get($syncProfile->getIntegrationType());
        $this->queue->enqueue($jobClass, [
            'tenant_id' => $syncProfile->tenant_id,
        ], QueueServiceLevel::Batch);
    }

    protected function getAccountingSyncProfiles(IntegrationType $integrationType): iterable
    {
        return AccountingSyncProfile::queryWithoutMultitenancyUnsafe()
            ->join(OAuthAccount::class, 'tenant_id', 'tenant_id')
            ->where('AccountingSyncProfiles.integration', $integrationType->value)
            ->where('OAuthAccounts.integration', $integrationType->value)
            ->all();
    }
}
