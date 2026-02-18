<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\FlywireSyncJob;
use Doctrine\DBAL\Connection;

class FlywireSync extends AbstractTaskQueueCronJob
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Queue $queue,
    ) {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return $this->connection->fetchAllAssociative(
            'SELECT tenant_id,id FROM MerchantAccounts WHERE deleted=0 AND gateway="flywire"'
        );
    }

    /**
     * @param array $task
     */
    public function runTask(mixed $task): bool
    {
        $company = Company::find($task['tenant_id']);
        if (null === $company || !$company->billingStatus()->isActive()) {
            return false;
        }

        $this->queue->enqueue(FlywireSyncJob::class, [
            'merchantAccountId' => $task['id'],
            'tenant_id' => $task['tenant_id'],
        ], QueueServiceLevel::Batch);

        return true;
    }
}
