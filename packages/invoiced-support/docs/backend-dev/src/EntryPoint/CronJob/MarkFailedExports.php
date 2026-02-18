<?php

namespace App\EntryPoint\CronJob;

use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\Core\Utils\InfuseUtility as U;
use App\Exports\Models\Export;

/**
 * Marks hung pending export processes as failed.
 */
class MarkFailedExports extends AbstractTaskQueueCronJob
{
    const BATCH_SIZE = 250;

    private int $count;

    public function __construct(
        private TenantContext $tenant,
        private Mailer $mailer,
    ) {
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
     * @param Export $task
     */
    public function runTask(mixed $task): bool
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($task->tenant());

        $task->status = Export::FAILED;
        $task->save();

        // notify user (if started in past day)
        if (time() - $task->created_at < 86400) {
            $task->notify($this->mailer);
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
        $t = U::unixToDb(time() - Export::MAX_EXECUTION_TIME);

        return Export::queryWithoutMultitenancyUnsafe()
            ->where('status', Export::PENDING)
            ->where('created_at', $t, '<');
    }
}
