<?php

namespace App\EntryPoint\CronJob;

use App\Core\Multitenant\TenantContext;
use App\AccountsReceivable\Libs\ExpiredDiscountCleaner;
use App\AccountsReceivable\Models\Discount;

class DeleteExpiredDiscounts extends AbstractTaskQueueCronJob
{
    private const BATCH_SIZE = 250;

    private int $count;

    public function __construct(private ExpiredDiscountCleaner $cleaner, private TenantContext $tenant)
    {
    }

    public static function getLockTtl(): int
    {
        return 59;
    }

    public function getTasks(): iterable
    {
        $query = $this->cleaner->getExpiredDiscounts();
        $this->count = $query->count();

        return $query->first(self::BATCH_SIZE);
    }

    public function getTaskCount(): int
    {
        return $this->count;
    }

    /**
     * @param Discount $task
     */
    public function runTask(mixed $task): bool
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($task->tenant());

        $processed = $this->cleaner->handleExpiredDiscount($task);

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return $processed;
    }
}
