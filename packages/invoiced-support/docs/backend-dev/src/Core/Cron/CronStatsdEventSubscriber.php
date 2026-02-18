<?php

namespace App\Core\Cron;

use App\Core\Cron\Events\CronJobBeginEvent;
use App\Core\Cron\Events\CronJobFinishedEvent;
use App\Core\Cron\Events\ScheduleRunBeginEvent;
use App\Core\Cron\Events\ScheduleRunFinishedEvent;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to cron events and outputs the results to Statsd.
 */
class CronStatsdEventSubscriber implements EventSubscriberInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;
    private array $timings = [];

    public function __construct(private HubInterface $hub)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CronJobBeginEvent::NAME => 'beginCronJob',
            CronJobFinishedEvent::NAME => 'finishedCronJob',
            ScheduleRunBeginEvent::NAME => 'beginScheduleRun',
            ScheduleRunFinishedEvent::NAME => 'finishedScheduleRun',
        ];
    }

    public function beginCronJob(CronJobBeginEvent $event): void
    {
        // Tag the active job on Sentry
        $this->hub->configureScope(function (Scope $scope) use ($event): void {
            $scope->setTag('cronJob', $event->getJobId());
        });

        $id = $event->getJobId();
        $this->statsd->increment('cron.started_job', 1, ['cron_job' => $id]);
        $this->timings[$id] = microtime(true);
    }

    public function finishedCronJob(CronJobFinishedEvent $event): void
    {
        $id = $event->getJobId();
        $time = round((microtime(true) - $this->timings[$id]) * 1000);
        $this->statsd->increment('cron.finished_job', 1, ['cron_job' => $id, 'job_result' => $event->getResult()]);
        $this->statsd->timing('cron.job_run_time', $time, 1, ['cron_job' => $id]);
    }

    public function beginScheduleRun(ScheduleRunBeginEvent $event): void
    {
        $this->statsd->increment('cron.schedule_run_start');
    }

    public function finishedScheduleRun(ScheduleRunFinishedEvent $event): void
    {
        $this->statsd->increment('cron.schedule_run_finish');
    }
}
