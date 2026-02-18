<?php

namespace App\Core\Queue;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\DebugContext;
use DateTime;
use Resque;
use ResqueScheduler;

class Queue
{
    public function __construct(
        private ResqueInitializer $resqueInitializer,
        private TenantContext $tenant,
        private DebugContext $debugContext
    ) {
    }

    /**
     * Enqueues a job for execution by a background worker.
     *
     * @param string $job         job class
     * @param array  $args        optional arguments
     * @param bool   $trackStatus monitors the status of a job when true
     *
     * @return string|bool Job ID when the job was created, false if creation was canceled due to beforeEnqueue
     */
    public function enqueue(string $job, array $args = [], ?QueueServiceLevel $queue = null, bool $trackStatus = false)
    {
        $queue ??= QueueServiceLevel::Normal;
        $this->resqueInitializer->initialize();
        $args = $this->enrichJobArgs($job, $args);

        // queue the proxy job class in order to add Symfony dependency injection
        return Resque::enqueue($queue->value, ProxyResqueJob::class, $args, $trackStatus);
    }

    /**
     * Enqueues a job for execution by a background worker at a
     * specific timestamp. Note that that does not guarantee exact
     * execution time, just that it will not be enqueued any earlier than the specified time.
     *
     * @param string $job  job class
     * @param array  $args optional arguments
     */
    public function enqueueAt(DateTime $at, string $job, array $args = [], ?QueueServiceLevel $queue = null): void
    {
        $queue ??= QueueServiceLevel::Normal;
        $this->resqueInitializer->initialize();
        $args = $this->enrichJobArgs($job, $args);
        $args['_scheduled'] = true;

        // queue the proxy job class in order to add Symfony dependency injection
        ResqueScheduler::enqueueAt($at, $queue->value, ProxyResqueJob::class, $args);
    }

    private function enrichJobArgs(string $job, array $args): array
    {
        // store the original requested job class as an argument
        $args['_job_class'] = $job;

        // set the tenant context on tenant-specific jobs to the current
        // context if a tenant was not already given
        if (!isset($args['tenant_id']) && is_a($job, TenantAwareQueueJobInterface::class, true) && $this->tenant->has()) {
            $args['tenant_id'] = $this->tenant->get()->id();
        }

        // set the correlation id on the job, if not already provided
        if (!isset($args['_correlation_id'])) {
            $args['_correlation_id'] = $this->debugContext->getCorrelationId();
        }

        return $args;
    }
}
