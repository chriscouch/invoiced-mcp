<?php

namespace App\EntryPoint\CronJob;

use App\Chasing\Legacy\InvoiceChasingScheduler;
use App\Core\Multitenant\TenantContext;
use App\AccountsReceivable\Models\Invoice;

/**
 * Calculates the next chase date for any dirty invoices.
 *
 * @deprecated
 */
class CalculateInvoiceChaseTimes extends AbstractTaskQueueCronJob
{
    private const BATCH_SIZE = 250;

    private int $count;

    public function __construct(private TenantContext $tenant)
    {
    }

    public static function getName(): string
    {
        return 'calculate_legacy_chase_times';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        $scheduler = new InvoiceChasingScheduler();
        $query = $scheduler->getDirtyInvoices();
        $this->count = $query->count();

        return $query->first(self::BATCH_SIZE);
    }

    public function getTaskCount(): int
    {
        return $this->count;
    }

    /**
     * @param Invoice $task
     */
    public function runTask(mixed $task): bool
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($task->tenant());

        $scheduler = new InvoiceChasingScheduler();
        [$next, $action] = $scheduler->calculateNextChase($task);

        $task->next_chase_on = $next;
        $task->next_chase_step = $action;
        $task->recalculate_chase = false;
        $task->skipReconciliation();
        $saved = $task->save();

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return $saved;
    }
}
