<?php

namespace App\EntryPoint\CronJob;

use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\EarthClassMailSyncJob;
use App\Integrations\EarthClassMail\Models\EarthClassMailAccount;

/**
 * Imports checks from Earth Class Mail as unapplied payments.
 */
class EarthClassMailSync extends AbstractTaskQueueCronJob
{
    public function __construct(private Queue $queue)
    {
    }

    public static function getLockTtl(): int
    {
        return 3600;
    }

    public function getTasks(): iterable
    {
        return EarthClassMailAccount::queryWithoutMultitenancyUnsafe()
            ->all();
    }

    /**
     * @param EarthClassMailAccount $task
     */
    public function runTask(mixed $task): bool
    {
        $this->queue->enqueue(EarthClassMailSyncJob::class, [
            'account_id' => $task->id(),
            'tenant_id' => $task->tenant_id,
        ], QueueServiceLevel::Batch);

        return true;
    }
}
