<?php

namespace App\EntryPoint\QueueJob;

use App\Chasing\LateFees\LateFeeAssessor;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;

/**
 * Assesses late fees for all customers belonging to a late fee schedule in the queue.
 */
class RunLateFeeScheduleJob extends AbstractResqueJob implements TenantAwareQueueJobInterface
{
    public function __construct(private LateFeeAssessor $lateFeeAssessor)
    {
    }

    public function perform(): void
    {
        if ($schedule = $this->getSchedule()) {
            $this->lateFeeAssessor->assess($schedule);
        }
    }

    /**
     * Gets the late fee schedule for this job.
     */
    public function getSchedule(): ?LateFeeSchedule
    {
        return LateFeeSchedule::find($this->args['schedule']);
    }
}
