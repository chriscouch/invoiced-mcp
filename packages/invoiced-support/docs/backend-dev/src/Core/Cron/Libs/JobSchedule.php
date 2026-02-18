<?php

namespace App\Core\Cron\Libs;

use App\Core\Cron\Events\ScheduleRunBeginEvent;
use App\Core\Cron\Events\ScheduleRunFinishedEvent;
use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\Models\CronJob;
use App\Core\Cron\ValueObjects\CronDate;
use App\Core\Cron\ValueObjects\Run;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;

class JobSchedule implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    private EventDispatcher $dispatcher;

    public function __construct(private array $jobs, private ServiceLocator $jobLocator, private LockFactory $lockFactory, iterable $subscribers, private string $namespace = '')
    {
        $this->dispatcher = new EventDispatcher();
        foreach ($subscribers as $subscriber) {
            $this->dispatcher->addSubscriber($subscriber);
        }
    }

    /**
     * Gets all of the available scheduled jobs.
     */
    public function getAllJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Gets the event dispatcher.
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->dispatcher;
    }

    /**
     * Registers a listener for an event.
     */
    public function listen(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    /**
     * Registers an event subscriber.
     */
    public function subscribe(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    /**
     * Gets all of the jobs scheduled to run, now.
     *
     * @return \Generator array(model => CronJob)
     */
    public function getScheduledJobs(): \Generator
    {
        foreach ($this->jobs as $job) {
            if ($job = $this->findOrCreateJob($job)) {
                yield $job;
            }
        }
    }

    /**
     * Gets a specific job, regardless of whether or not it's scheduled to run.
     */
    public function getSingleJob(string $jobId): ?array
    {
        foreach ($this->jobs as $job) {
            /** @var CronJobInterface $jobClass */
            $jobClass = $job['class'];
            if ($jobClass::getName() == $jobId) {
                return $this->findOrCreateJob($job, false);
            }
        }

        return null;
    }

    /**
     * Runs any scheduled tasks.
     *
     * @return bool true if all tasks ran successfully
     */
    public function runScheduled(SymfonyStyle $output): bool
    {
        $success = true;

        $event = new ScheduleRunBeginEvent();
        $this->dispatcher->dispatch($event, $event::NAME);

        foreach ($this->getScheduledJobs() as $jobInfo) {
            $job = $jobInfo['model'];
            $run = $this->runJob($job, $jobInfo, $output);

            $success = $run->succeeded() && $success;
        }

        $event = new ScheduleRunFinishedEvent();
        $this->dispatcher->dispatch($event, $event::NAME);

        return $success;
    }

    /**
     * Runs a specified task, regardless of whether it's scheduled to run.
     *
     * @return bool true if task ran successfully
     */
    public function runSingleJob(string $jobId, SymfonyStyle $io): bool
    {
        $event = new ScheduleRunBeginEvent();
        $this->dispatcher->dispatch($event, $event::NAME);

        $jobInfo = $this->getSingleJob($jobId);
        if (!$jobInfo) {
            $io->error('Cron job could not be located or is locked: '.$jobId);

            return false;
        }

        $job = $jobInfo['model'];
        $run = $this->runJob($job, $jobInfo, $io);

        $event = new ScheduleRunFinishedEvent();
        $this->dispatcher->dispatch($event, $event::NAME);

        return $run->succeeded();
    }

    private function findOrCreateJob(array $job, bool $checkSchedule = true): ?array
    {
        // only run the job if we can get the lock
        /** @var CronJobInterface $jobClass */
        $jobClass = $job['class'];
        $lock = new Lock($jobClass::getName(), $this->lockFactory, $this->namespace);
        if (!$lock->acquire($jobClass::getLockTtl())) {
            return null;
        }

        $jobName = $jobClass::getName();
        $model = CronJob::find($jobName);

        // create a new model if this is the job's first run
        if (!$model) {
            $model = new CronJob();
            $model->id = $jobName;
            $model->save();
        }

        // check if scheduled to run - and only if it has ran before
        if ($checkSchedule && $lastRan = $model->last_ran) {
            $date = new CronDate($job['schedule'], $lastRan);
            if ($date->getNextRun() > time()) {
                $lock->release();

                return null;
            }
        }

        $job['model'] = $model;
        $job['lock'] = $lock;

        return $job;
    }

    /**
     * Runs a scheduled job.
     *
     * @param array $jobInfo ['model' - cron job model
     *                       'lock' - lock aquired for the job
     *                       ]
     */
    private function runJob(CronJob $jobModel, array $jobInfo, SymfonyStyle $io): Run
    {
        $io->title("{$jobModel->id}");
        $io->comment('Starting cron job...');

        // set up the runner
        $job = $this->jobLocator->get($jobInfo['class']);

        $runner = new Runner($jobModel, $job, $this->dispatcher);
        if (isset($this->logger)) {
            $runner->setLogger($this->logger);
        }

        // set up an object to track this run
        $run = new Run();
        $run->setConsoleOutput($io);

        // and go!
        $runner->go($run);
        $jobInfo['lock']->release();

        if ($run->succeeded()) {
            $io->success('Cron job completed successfully');
        } elseif (Run::RESULT_LOCKED == $run->getResult()) {
            $io->error('Cron job is locked');
        } elseif ($run->failed()) {
            $io->error('Cron job failed');
        }

        return $run;
    }
}
