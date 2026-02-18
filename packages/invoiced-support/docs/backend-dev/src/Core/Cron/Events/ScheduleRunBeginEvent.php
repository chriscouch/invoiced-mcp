<?php

namespace App\Core\Cron\Events;

use Symfony\Contracts\EventDispatcher\Event;

class ScheduleRunBeginEvent extends Event
{
    const NAME = 'schedule_run.begin';
}
