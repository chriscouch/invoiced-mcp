<?php

namespace App\EntryPoint\CronJob;

use App\Chasing\LateFees\LateFeeAssessor;
use App\Chasing\Models\LateFeeSchedule;

class LateFeeJob extends AbstractTaskQueueCronJob
{
    public function __construct(private LateFeeAssessor $lateFeeAssessor)
    {
    }

    public static function getName(): string
    {
        return 'late_fees';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return LateFeeSchedule::queryWithoutMultitenancyUnsafe()
            ->where('enabled', true)
            ->all();
    }

    /**
     * @param LateFeeSchedule $task
     */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();

        // check if the company is in good standing
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // queue the schedule to have late fees assessed
        $this->lateFeeAssessor->queue($task);

        return true;
    }
}
