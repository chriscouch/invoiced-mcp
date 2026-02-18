<?php

namespace App\Core\Queue\Events;

use Resque_Job;
use Throwable;

class OnFailureEvent
{
    public function __construct(private Throwable $exception, private Resque_Job $job)
    {
    }

    public function getException(): Throwable
    {
        return $this->exception;
    }

    public function getJob(): Resque_Job
    {
        return $this->job;
    }
}
