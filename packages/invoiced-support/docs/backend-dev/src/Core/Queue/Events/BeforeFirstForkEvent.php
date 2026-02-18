<?php

namespace App\Core\Queue\Events;

use Resque_Worker;

class BeforeFirstForkEvent
{
    public function __construct(private Resque_Worker $worker)
    {
    }

    public function getWorker(): Resque_Worker
    {
        return $this->worker;
    }
}
