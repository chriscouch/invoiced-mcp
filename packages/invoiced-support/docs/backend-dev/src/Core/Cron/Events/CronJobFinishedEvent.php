<?php

namespace App\Core\Cron\Events;

use Symfony\Contracts\EventDispatcher\Event;

class CronJobFinishedEvent extends Event
{
    const NAME = 'cron_job.finished';

    public function __construct(protected string $jobId, protected string $result)
    {
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getResult(): string
    {
        return $this->result;
    }
}
