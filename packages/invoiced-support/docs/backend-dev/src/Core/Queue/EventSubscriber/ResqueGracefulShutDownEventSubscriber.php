<?php

namespace App\Core\Queue\EventSubscriber;

use App\Core\Queue\Events\BeforeFirstForkEvent;
use App\Core\Queue\Events\BeforeForkEvent;
use App\Core\Queue\Events\DoneWorkingEvent;
use App\Core\Queue\QueueServiceLevel;
use App\Core\Queue\Resque;
use App\Core\Utils\AwsMetadataClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResqueGracefulShutDownEventSubscriber implements EventSubscriberInterface
{
    private bool $hasProtection = false;
    private int $startTime;

    public function __construct(
        private readonly AwsMetadataClient $awsMetadataClient,
        private Resque $resque,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeFirstForkEvent::class => 'beforeFirstFork',
            BeforeForkEvent::class => 'beforeFork',
            DoneWorkingEvent::class => 'doneWorking',
        ];
    }

    public function beforeFirstFork(): void
    {
        $this->startTime = time();
    }

    public function beforeFork(BeforeForkEvent $event): void
    {
        $queue = QueueServiceLevel::from($event->job->queue);
        if (QueueServiceLevel::Batch == $queue) {
            $this->awsMetadataClient->enableProtection();
            $this->hasProtection = true;
        }
    }

    public function doneWorking(): void
    {
        if ($this->hasProtection) {
            $this->awsMetadataClient->disableProtection();
            $this->hasProtection = false;

            // This is a band-aid fix until we can ensure the ECS orchestrator
            // can shut down tasks after protection is disabled for the task
            // during a deployment. As it currently stands the task stays
            // running for 48 hours after a deployment because the time between
            // protection mode disabled and the next job is too short. Until this
            // can be solved we are going to shut down queue worker tasks gracefully
            // after at least 1 hour of processing jobs.
            if ((time() - $this->startTime) > 3600) {
                $this->resque->stopWorker();
            }
        }
    }
}
