<?php

namespace App\Core\Queue\EventSubscriber;

use App\Core\Queue\Events\AfterPerformEvent;
use App\Core\Queue\Events\BeforePerformEvent;
use App\Core\Queue\Events\OnFailureEvent;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use Carbon\Carbon;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Resque_Job_DontPerform;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\SemaphoreInterface;

/**
 * Controls resque job concurrency when configured.
 */
class ResqueConcurrencySubscriber implements EventSubscriberInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?SemaphoreInterface $currentSemaphore = null;

    public function __construct(private SemaphoreFactory $semaphoreFactory, private Queue $queue)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforePerformEvent::class => 'beforePerform',
            AfterPerformEvent::class => 'afterPerform',
            OnFailureEvent::class => 'onFailure',
        ];
    }

    /**
     * @throws Resque_Job_DontPerform if the job should be skipped
     */
    public function beforePerform(BeforePerformEvent $event): void
    {
        // check if the job should be concurrency controlled
        $job = $event->job;
        $jobArgs = $job->getArguments();
        $jobClass = $jobArgs['_job_class'];
        if (!is_a($jobClass, MaxConcurrencyInterface::class, true)) {
            return;
        }

        $limit = $jobClass::getMaxConcurrency($jobArgs);
        $key = $jobClass::getConcurrencyKey($jobArgs);
        $ttl = (float) $jobClass::getConcurrencyTtl($jobArgs);

        // Use a semaphore to limit the number of concurrent
        // processes that this job can use.
        $semaphore = $this->semaphoreFactory->createSemaphore($key, $limit, 1, $ttl);
        if (!$semaphore->acquire()) {
            // add the job back to the queue to run in the future
            if ($jobClass::delayAtConcurrencyLimit()) {
                $jobArgs['_concurrency_retries'] = $jobArgs['_concurrency_retries'] ?? 0;
                $backoff = $this->calcDelay(1000, 300000, $jobArgs['_concurrency_retries'], $jobArgs['_backoff'] ?? null);
                $jobArgs['_backoff'] = $backoff;
                ++$jobArgs['_concurrency_retries'];
                $backoffSeconds = (int) round($backoff / 1000);
                $queue = QueueServiceLevel::from($job->queue);
                $this->queue->enqueueAt(Carbon::now()->addSeconds($backoffSeconds), $jobClass, $jobArgs, $queue); /* @phpstan-ignore-line */
                $this->logger->notice('Delaying '.$job->queue.' job for '.$backoffSeconds.' seconds due to concurrency limit', ['args' => $jobArgs, 'job' => $jobClass]);
            } else {
                $this->logger->notice('Killing '.$job->queue.' job  due to concurrency limit', ['args' => $jobArgs, 'job' => $jobClass]);
            }

            throw new Resque_Job_DontPerform();
        }

        $this->currentSemaphore = $semaphore;
    }

    public function afterPerform(AfterPerformEvent $event): void
    {
        // if the job is using a semaphore then release it
        if ($this->currentSemaphore) {
            $this->currentSemaphore->release();
            $this->currentSemaphore = null;
        }
    }

    public function onFailure(OnFailureEvent $event): void
    {
        // if the job is using a semaphore then release it
        if ($this->currentSemaphore) {
            $this->currentSemaphore->release();
            $this->currentSemaphore = null;
        }
    }

    /**
     * Calculates a backoff (in ms) for a job that has reached its
     * concurrency limit to be retried in the future. This
     * calculation is using exponential backoff + jitter. The
     * jitter is added to minimize competition of future calls.
     *
     * jitter = random_between(base, last * 3)
     * T() = min(cap, 2 ^ retries + jitter)
     */
    private function calcDelay(int $base, int $cap, int $retries, ?int $last): int
    {
        $last ??= $base;
        $jitter = random_int($base, $last * 3);
        $exponentialBackoff = (2 ** $retries) * 1000;

        return min($cap, $exponentialBackoff + $jitter);
    }
}
