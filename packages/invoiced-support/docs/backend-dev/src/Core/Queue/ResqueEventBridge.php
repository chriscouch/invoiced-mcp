<?php

namespace App\Core\Queue;

use App\Core\Queue\Events\AfterEnqueueEvent;
use App\Core\Queue\Events\AfterForkEvent;
use App\Core\Queue\Events\AfterPerformEvent;
use App\Core\Queue\Events\AfterScheduleEvent;
use App\Core\Queue\Events\BeforeDelayedEnqueueEvent;
use App\Core\Queue\Events\BeforeEnqueueEvent;
use App\Core\Queue\Events\BeforeFirstForkEvent;
use App\Core\Queue\Events\BeforeForkEvent;
use App\Core\Queue\Events\BeforePerformEvent;
use App\Core\Queue\Events\DoneWorkingEvent;
use App\Core\Queue\Events\OnFailureEvent;
use Carbon\CarbonImmutable;
use DateTime;
use Resque_Event;
use Resque_Job;
use Resque_Worker;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Bridges resque events with the Symfony EventDispatcher component.
 */
class ResqueEventBridge
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function register(): void
    {
        // Core php-resque events
        Resque_Event::listen('beforeEnqueue', [$this, 'beforeEnqueue']);
        Resque_Event::listen('afterEnqueue', [$this, 'afterEnqueue']);
        Resque_Event::listen('beforeFirstFork', [$this, 'beforeFirstFork']);
        Resque_Event::listen('beforeFork', [$this, 'beforeFork']);
        Resque_Event::listen('afterFork', [$this, 'afterFork']);
        Resque_Event::listen('beforePerform', [$this, 'beforePerform']);
        Resque_Event::listen('afterPerform', [$this, 'afterPerform']);
        Resque_Event::listen('onFailure', [$this, 'onFailure']);
        Resque_Event::listen('doneWorking', [$this, 'doneWorking']);

        // php-resque-scheduler events
        Resque_Event::listen('beforeDelayedEnqueue', [$this, 'beforeDelayedEnqueue']);
        Resque_Event::listen('afterSchedule', [$this, 'afterSchedule']);
    }

    public function beforeEnqueue(string $class, array $args, string $queue, string $id): void
    {
        $this->dispatcher->dispatch(new BeforeEnqueueEvent($class, $args, $queue, $id));
    }

    public function afterEnqueue(string $class, array $args, string $queue, string $id): void
    {
        $this->dispatcher->dispatch(new AfterEnqueueEvent($class, $args, $queue, $id));
    }

    public function beforeFirstFork(Resque_Worker $worker): void
    {
        $this->dispatcher->dispatch(new BeforeFirstForkEvent($worker));
    }

    public function beforeFork(Resque_Job $job): void
    {
        $this->dispatcher->dispatch(new BeforeForkEvent($job));
    }

    public function afterFork(Resque_Job $job): void
    {
        $this->dispatcher->dispatch(new AfterForkEvent($job));
    }

    public function beforePerform(Resque_Job $job): void
    {
        $this->dispatcher->dispatch(new BeforePerformEvent($job));
    }

    public function afterPerform(Resque_Job $job): void
    {
        $this->dispatcher->dispatch(new AfterPerformEvent($job));
    }

    public function onFailure(Throwable $e, Resque_Job $job): void
    {
        $this->dispatcher->dispatch(new OnFailureEvent($e, $job));
    }

    public function beforeDelayedEnqueue(string $queue, string $class, array $args): void
    {
        $this->dispatcher->dispatch(new BeforeDelayedEnqueueEvent($queue, $class, $args[0]));
    }

    public function afterSchedule(mixed $at, string $queue, string $class, array $args): void
    {
        if (is_int($at)) {
            $at = CarbonImmutable::createFromTimestamp($at);
        } elseif ($at instanceof DateTime) {
            $at = CarbonImmutable::createFromMutable($at);
        }

        $this->dispatcher->dispatch(new AfterScheduleEvent($at, $queue, $class, $args));
    }

    public function doneWorking(Resque_Worker $worker): void
    {
        $this->dispatcher->dispatch(new DoneWorkingEvent($worker));
    }
}
