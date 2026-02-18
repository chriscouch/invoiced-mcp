<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Statsd\StatsdAwareTrait;
use App\EntryPoint\QueueJob\BillSubscriptionsJob;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

/**
 * A cron job that bills subscriptions as they become due for billing.
 * This job will start a queue job for each tenant that has at
 * least one subscription that is due.
 */
class BillSubscriptions extends AbstractTaskQueueCronJob
{
    use StatsdAwareTrait;

    public function __construct(
        private Connection $connection,
        private Queue $queue,
    ) {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        $t = CarbonImmutable::now()->getTimestamp();

        return $this->connection->fetchFirstColumn(
            "SELECT id
                  FROM Companies
                  WHERE canceled = 0
                    AND id IN (SELECT tenant_id
                               FROM Subscriptions
                               WHERE finished = false
                                 AND pending_renewal = false
                                 AND paused = false
                                 AND renews_next IS NOT NULL
                                 AND renews_next <= $t
                               GROUP BY tenant_id)"
        );
    }

    /**
     * @param int $task
     */
    public function runTask(mixed $task): bool
    {
        $company = Company::find($task);
        if (null === $company || !$company->billingStatus()->isActive() || !$company->features->has('subscription_billing')) {
            return false;
        }

        $this->queue->enqueue(BillSubscriptionsJob::class, [
            'tenant_id' => $task,
        ], QueueServiceLevel::Batch);

        return true;
    }
}
