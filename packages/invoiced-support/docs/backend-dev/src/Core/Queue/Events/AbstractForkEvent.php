<?php

namespace App\Core\Queue\Events;

use Resque_Job;

abstract class AbstractForkEvent
{
    public function __construct(public readonly Resque_Job $job)
    {
    }
}
