<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\Webhooks\Models\WebhookAttempt;
use App\Core\Orm\Query;

class WebhookRetries extends AbstractTaskQueueCronJob
{
    const BATCH_SIZE = 1000;

    private int $count;

    public function __construct(private TenantContext $tenant, private Queue $queue)
    {
    }

    public static function getName(): string
    {
        return 'retry_webhooks';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        $query = $this->getAttempts();
        $this->count = $query->count();

        return $query->first(self::BATCH_SIZE);
    }

    public function getTaskCount(): int
    {
        return $this->count;
    }

    /**
     * @param WebhookAttempt $task
     */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();

        // check if the company is in good standing
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        // should not retry an attempt that already succeeded
        if ($task->succeeded()) {
            $task->next_attempt = null;
            $task->save();
        } else {
            $task->queue($this->queue);
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return true;
    }

    /**
     * Gets all webhook attempts that need to be retried.
     *
     * @param Company $company used for testing
     */
    public function getAttempts(Company $company = null): Query
    {
        if ($company) {
            $query = WebhookAttempt::queryWithTenant($company);
        } else {
            $query = WebhookAttempt::queryWithoutMultitenancyUnsafe();
        }

        return $query->join(Company::class, 'tenant_id', 'Companies.id')
            ->where('next_attempt IS NOT NULL')
            ->where('next_attempt', time(), '<=')
            ->where('Companies.canceled=0');
    }
}
