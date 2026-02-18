<?php

namespace App\Core\Queue\Events;

use Carbon\CarbonImmutable;

class AfterScheduleEvent extends AbstractEnqueueEvent
{
    public function __construct(private CarbonImmutable $at, string $queue, string $class, array $args)
    {
        parent::__construct($class, $args, $queue, '');
    }

    public function getAt(): CarbonImmutable
    {
        return $this->at;
    }
}
