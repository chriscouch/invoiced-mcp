<?php

namespace App\Core\Queue\EventSubscriber;

use App\Core\Queue\Events\AbstractEnqueueEvent;
use App\Core\Queue\Events\AfterEnqueueEvent;
use App\Core\Queue\Events\AfterPerformEvent;
use App\Core\Queue\Events\AfterScheduleEvent;
use App\Core\Queue\Events\BeforeDelayedEnqueueEvent;
use App\Core\Queue\Events\BeforeForkEvent;
use App\Core\Queue\Events\BeforePerformEvent;
use App\Core\Queue\Events\OnFailureEvent;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\DebugContext;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Resque_Job;
use Resque_Job_DirtyExitException;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs important resque events to statsd. Also sets the
 * queue context in Sentry.
 */
class ResqueStatsdSubscriber implements EventSubscriberInterface, LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    public function __construct(
        private HubInterface $hub,
        private DebugContext $debugContext,
    ) {
    }

    /**
     * Submit metrics for a queue and job whenever a job is pushed to a queue.
     * This intentionally excludes jobs which are enqueued that were scheduled.
     */
    public function afterEnqueue(AfterEnqueueEvent $event): void
    {
        if (!isset($event->getArgs()['_scheduled'])) {
            $this->statsd->increment('resque.enqueued', 1.0, $this->getEventTags($event));
        }
    }

    /**
     * Begin tracking execution time before forking out to run a job in a php-resque worker
     * and submits the metrics for the duration of a job spend waiting in the queue.
     *
     * Time tracking begins in `beforeFork` to ensure that the time spent for forking
     * and any hooks registered for `beforePerform` is also tracked.
     */
    public function beforeFork(BeforeForkEvent $event): void
    {
        $event->job->statsDStartTime = microtime(true); /* @phpstan-ignore-line */
        if (isset($event->job->payload['queue_time'])) {
            $queuedTime = round((microtime(true) - $event->job->payload['queue_time']) * 1000);
            $this->statsd->timing('resque.time_in_queue', $queuedTime, 1.0, $this->getJobTags($event->job));
        }
    }

    /**
     * Sets Sentry context for whenever a job is about to start.
     */
    public function beforePerform(BeforePerformEvent $event): void
    {
        $job = $event->job;
        $this->hub->configureScope(function (Scope $scope) use ($job): void {
            $scope->setTag('queue', $job->queue);
            if (isset($job->payload['args'][0]['_job_class'])) {
                // strip namespacing to get class name
                $paths = explode('\\', $job->payload['args'][0]['_job_class']);
                $scope->setTag('queueJob', end($paths));
            }
            $scope->setExtra('payload', json_encode($job->payload));
            if (isset($job->payload['id'])) {
                $scope->setTag('jobId', $job->payload['id']);
            }
        });

        if (isset($job->payload['id'])) {
            $this->debugContext->setRequestId($job->payload['id']);
        }
        if (isset($job->payload['args'][0]['_correlation_id'])) {
            $this->debugContext->setCorrelationId($job->payload['args'][0]['_correlation_id']);
        }
    }

    /**
     * Submit metrics for whenever a job is finished.
     */
    public function afterPerform(AfterPerformEvent $event): void
    {
        $job = $event->job;
        $this->statsd->increment('resque.finished_job', 1.0, $this->getJobTags($job));
        if (property_exists($job, 'statsDStartTime')) {
            $executionTime = round((microtime(true) - $job->statsDStartTime) * 1000);
            $this->statsd->timing('resque.job_run_time', $executionTime, 1.0, $this->getJobTags($job));
        }
    }

    /**
     * Submit metrics for a queue and job whenever a job fails to run.
     */
    public function onFailure(OnFailureEvent $event): void
    {
        $job = $event->getJob();
        $this->statsd->increment('resque.failed_job', 1.0, $this->getJobTags($job));

        $e = $event->getException();
        if ($e instanceof Resque_Job_DirtyExitException) {
            $logTags = [
                'exception' => $e,
                'queue' => $job->queue,
                'payload' => $job->payload,
            ];
            if (isset($job->payload['args'][0]['_job_class'])) {
                // strip namespacing to get class name
                $paths = explode('\\', $job->payload['args'][0]['_job_class']);
                $logTags['queueJob'] = end($paths);
            }
            $this->logger->error('Resque dirty exit', $logTags);
            $this->statsd->increment('resque.dirty_exit', 1.0, $this->getJobTags($job));
        }
    }

    /**
     * Submit metrics for a queue and job whenever a job is scheduled in php-resque-scheduler.
     */
    public function afterSchedule(AfterScheduleEvent $event): void
    {
        $this->statsd->increment('resque.scheduled', 1.0, $this->getEventTags($event));
    }

    public function beforeDelayedEnqueue(BeforeDelayedEnqueueEvent $event): void
    {
        $this->statsd->increment('resque.enqueue_scheduled', 1.0, $this->getEventTags($event));
    }

    private function getJobTags(Resque_Job $job): array
    {
        $tags = ['queue' => $job->queue];

        if (isset($job->payload['args'][0]['_job_class'])) {
            // strip namespacing to get class name
            $paths = explode('\\', $job->payload['args'][0]['_job_class']);
            $tags['job'] = end($paths);
        }

        return $tags;
    }

    private function getEventTags(AbstractEnqueueEvent $event): array
    {
        $tags = ['queue' => $event->getQueue()];

        $args = $event->getArgs();
        if (isset($args['_job_class'])) {
            // strip namespacing to get class name
            $paths = explode('\\', $args['_job_class']);
            $tags['job'] = end($paths);
        }

        return $tags;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEnqueueEvent::class => 'afterEnqueue',
            BeforeForkEvent::class => 'beforeFork',
            BeforePerformEvent::class => ['beforePerform', 256], // Should happen before any other listeners to this event
            AfterPerformEvent::class => 'afterPerform',
            OnFailureEvent::class => 'onFailure',
            AfterScheduleEvent::class => 'afterSchedule',
            BeforeDelayedEnqueueEvent::class => 'beforeDelayedEnqueue',
        ];
    }
}
