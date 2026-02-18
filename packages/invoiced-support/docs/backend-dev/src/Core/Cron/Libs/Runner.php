<?php

namespace App\Core\Cron\Libs;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use Psr\Log\LoggerAwareInterface;
use Throwable;
use App\Core\Cron\Events\CronJobBeginEvent;
use App\Core\Cron\Events\CronJobFinishedEvent;
use App\Core\Cron\Models\CronJob;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Runner implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private CronJob $jobModel, private CronJobInterface $job, private EventDispatcher $dispatcher)
    {
    }

    /**
     * Gets the job model for this runner.
     */
    public function getJobModel(): CronJob
    {
        return $this->jobModel;
    }

    /**
     * Gets the job for this runner.
     */
    public function getJob(): CronJobInterface
    {
        return $this->job;
    }

    /**
     * Runs a scheduled job.
     */
    public function go(Run $run = null): Run
    {
        if (!$run) {
            $run = new Run();
        }

        // call the `cron_job.begin` event
        $event = new CronJobBeginEvent($this->jobModel->id);
        $this->dispatcher->dispatch($event, $event::NAME);
        if ($event->isPropagationStopped()) {
            $run->writeOutput('Rejected by cron_job.begin event listener');
            $run->setResult(Run::RESULT_FAILED);
        }

        // this is where the job actually gets called
        if (!$event->isPropagationStopped()) {
            $this->invoke($this->job, $run);
        }

        // perform post-run tasks:
        // call the `cron_job.finished` event
        $event = new CronJobFinishedEvent($this->jobModel->id, $run->getResult());
        $this->dispatcher->dispatch($event, $event::NAME);

        // persist the result
        $this->saveRun($run);

        return $run;
    }

    /**
     * Executes the actual job.
     */
    private function invoke(CronJobInterface $job, Run $run): Run
    {
        // start with assuming it's successful
        // cron jobs can overwrite the status
        $run->setResult(Run::RESULT_SUCCEEDED);

        try {
            $job->execute($run);
        } catch (Throwable $e) {
            if (isset($this->logger)) {
                $this->logger->error("An uncaught exception occurred while running the {$this->jobModel->id()} scheduled job.", ['exception' => $e]);
            }

            $run->writeOutput($e->getMessage());
            $run->setResult(Run::RESULT_FAILED);
        }

        return $run;
    }

    /**
     * Saves the run attempt.
     */
    private function saveRun(Run $run): void
    {
        $this->jobModel->last_ran = time();
        $this->jobModel->last_run_succeeded = $run->succeeded();
        $this->jobModel->last_run_output = $run->getOutput();
        $this->jobModel->save();
    }
}
