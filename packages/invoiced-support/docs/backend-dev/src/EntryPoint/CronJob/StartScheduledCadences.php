<?php

namespace App\EntryPoint\CronJob;

use App\Chasing\CustomerChasing\CustomerChasingRun;
use App\Chasing\Models\ChasingCadence;

class StartScheduledCadences extends AbstractTaskQueueCronJob
{
    public function __construct(private CustomerChasingRun $chasingRun)
    {
    }

    public static function getName(): string
    {
        return 'chasing';
    }

    public static function getLockTtl(): int
    {
        return 900;
    }

    public function getTasks(): iterable
    {
        // A cadence needs to run if:
        // 1) It is on or past the time of day it's scheduled, and
        // 2) the cadence has not ran today, and
        // 3) the cadence is not paused.

        return ChasingCadence::queryWithoutMultitenancyUnsafe()
            ->where('next_run IS NOT NULL')
            ->where('next_run', time(), '<=')
            ->where('paused', false)
            ->all();
    }

    /**
     * @param ChasingCadence $task
     */
    public function runTask(mixed $task): bool
    {
        // check if the company is in good standing
        $company = $task->tenant();
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        if (!$company->features->has('smart_chasing')) {
            return false;
        }

        $this->chasingRun->queue($task);

        return true;
    }
}
