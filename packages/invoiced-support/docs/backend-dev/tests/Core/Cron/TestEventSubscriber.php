<?php

namespace App\Tests\Core\Cron;

use App\Core\Cron\Events\ScheduleRunBeginEvent;
use App\Core\Cron\Events\ScheduleRunFinishedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TestEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ScheduleRunBeginEvent::NAME => 'runBegin',
            ScheduleRunFinishedEvent::NAME => 'runFinished',
        ];
    }

    public function runBegin(ScheduleRunBeginEvent $event): void
    {
    }

    public function runFinished(ScheduleRunFinishedEvent $event): void
    {
    }
}
