<?php

namespace App\EntryPoint\QueueJob;

use App\Chasing\CustomerChasing\CustomerChasingRun;
use App\Chasing\Models\ChasingCadence;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;

/**
 * Executes an individual chasing run in the queue.
 */
class RunChasingCadenceJob extends AbstractResqueJob implements TenantAwareQueueJobInterface
{
    public function __construct(private CustomerChasingRun $chasingRun)
    {
    }

    public function perform(): void
    {
        $cadence = $this->getCadence();
        if (!$cadence) {
            return;
        }

        $this->chasingRun->chase($cadence);
    }

    /**
     * Gets the cadence for this job.
     */
    public function getCadence(): ?ChasingCadence
    {
        $id = $this->args['cadence'];

        return ChasingCadence::queryWithoutMultitenancyUnsafe()
            ->where('id', $id)
            ->oneOrNull();
    }
}
