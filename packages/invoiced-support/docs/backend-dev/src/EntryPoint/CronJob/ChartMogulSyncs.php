<?php

namespace App\EntryPoint\CronJob;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\ChartMogulSyncJob;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;

class ChartMogulSyncs extends AbstractTaskQueueCronJob
{
    public function __construct(private Queue $queue)
    {
    }

    public static function getName(): string
    {
        return 'chartmogul_syncs';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return ChartMogulAccount::queryWithoutMultitenancyUnsafe()
            ->where('enabled', true)
            ->all();
    }

    /**
     * @param ChartMogulAccount $task
     */
    public function runTask(mixed $task): bool
    {
        // check if the company is in good standing
        if (!$task->tenant()->billingStatus()->isActive()) {
            return false;
        }

        // queue the sync
        $this->queue->enqueue(ChartMogulSyncJob::class, [
            'accountId' => $task->id(),
            'tenant_id' => $task->tenant_id,
        ], QueueServiceLevel::Batch);

        return true;
    }
}
