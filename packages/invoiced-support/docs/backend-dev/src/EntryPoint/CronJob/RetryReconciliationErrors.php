<?php

namespace App\EntryPoint\CronJob;

use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\AccountingSync\RetryReconciliationError;
use App\Integrations\Exceptions\IntegrationException;

class RetryReconciliationErrors extends AbstractTaskQueueCronJob
{
    public function __construct(
        private TenantContext $tenant,
        private RetryReconciliationError $retry,
    ) {
    }

    public static function getLockTtl(): int
    {
        return 900;
    }

    public function getTasks(): iterable
    {
        return ReconciliationError::queryWithoutMultitenancyUnsafe()
            ->where('retry', true)
            ->all();
    }

    /**
     * @param ReconciliationError $task
     */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        try {
            $this->retry->retry($task);
        } catch (IntegrationException) {
            return false;
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return true;
    }
}
