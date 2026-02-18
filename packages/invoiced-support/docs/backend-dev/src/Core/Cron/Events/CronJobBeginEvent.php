<?php

namespace App\Core\Cron\Events;

use Symfony\Contracts\EventDispatcher\Event;

class CronJobBeginEvent extends Event
{
    const NAME = 'cron_job.begin';

    public function __construct(protected string $jobId)
    {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }
}
