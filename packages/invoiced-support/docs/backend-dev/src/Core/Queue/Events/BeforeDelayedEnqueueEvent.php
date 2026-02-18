<?php

namespace App\Core\Queue\Events;

class BeforeDelayedEnqueueEvent extends AbstractEnqueueEvent
{
    public function __construct(string $queue, string $class, array $args)
    {
        parent::__construct($class, $args, $queue, '');
    }
}
