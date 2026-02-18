<?php

namespace App\EntryPoint\CronJob;

use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\Core\Utils\InfuseUtility as U;
use App\EntryPoint\QueueJob\ImportJob;
use App\Imports\Models\Import;

/**
 * Marks hung pending import processes as failed.
 */
class MarkFailedImports extends AbstractTaskQueueCronJob
{
    const BATCH_SIZE = 250;

    private int $count;

    public function __construct(private TenantContext $tenant, private ImportJob $importJob)
    {
    }

    public static function getLockTtl(): int
    {
        return 59;
    }

    public function getTasks(): iterable
    {
        $query = self::getFailedPendingJobs();
        $this->count = $query->count();

        return $query->first(self::BATCH_SIZE);
    }

    public function getTaskCount(): int
    {
        return $this->count;
    }

    /**
     * @param Import $task
     */
    public function runTask(mixed $task): bool
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($task->tenant());

        $task->status = Import::FAILED;
        $task->failure_detail = [['reason' => 'Your import did not complete successfully because it timed out.']];
        $task->save();

        // notify user (if started in past day)
        if (time() - $task->created_at < 86400) {
            $this->importJob->notify($task);
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return true;
    }

    /**
     * Gets the pending jobs that have taken too long.
     */
    public function getFailedPendingJobs(): Query
    {
        $t = U::unixToDb(time() - Import::MAX_INACTIVE_TIME);

        return Import::queryWithoutMultitenancyUnsafe()
            ->where('status', Import::PENDING)
            ->where('updated_at', $t, '<');
    }
}
