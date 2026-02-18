<?php

namespace App\EntryPoint\CronJob;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\MrrSyncJob;
use App\SubscriptionBilling\Models\SubscriptionBillingSettings;

class MrrSyncCronJob extends AbstractTaskQueueCronJob
{
    public function __construct(
        private Queue $queue,
    ) {
    }

    public static function getName(): string
    {
        return 'mrr_sync';
    }

    public static function getLockTtl(): int
    {
        return 600; // 5 minutes
    }

    public function getTasks(): iterable
    {
        return SubscriptionBillingSettings::queryWithoutMultitenancyUnsafe()
            ->where('mrr_version_id', null, '<>')
            ->all();
    }

    /**
     * @param SubscriptionBillingSettings $task
     */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();

        // check if the company is in good standing
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // must have subscriptions enabled
        if (!$company->features->has('subscriptions')) {
            return false;
        }

        $this->queue->enqueue(MrrSyncJob::class, [
            'tenant_id' => $company->id,
        ], QueueServiceLevel::Batch);

        return true;
    }
}
