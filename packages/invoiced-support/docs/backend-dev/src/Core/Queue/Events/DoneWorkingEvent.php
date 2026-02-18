<?php

namespace App\Core\Queue\Events;

use Resque_Worker;

class DoneWorkingEvent
{
    public function __construct(public readonly Resque_Worker $worker)
    {
    }
}
