<?php

declare(ticks=1);

namespace App\Core\Queue;

use Resque_Event;
use ResqueScheduler_Worker;
use Stringable;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;

class ResqueSchedulerWorker implements Stringable
{
    /**
     * Active scheduler worker key.
     *
     * Used to store the started scheduler worker PID (in a Redis string).
     */
    const SCHEDULER_WORKER_KEY = 'scheduler_workers';

    private string $id;
    private string $hostname;
    private bool $shutdown = false;
    private ResqueScheduler_Worker $internalWorker;

    public function __construct(?string $id = null)
    {
        // generate a worker ID if not given in the form of "hostname:PID"
        if (!$id) {
            $this->hostname = php_uname('n');
            $this->id = $this->hostname.':'.getmypid();
        } else {
            $this->id = $id;
            [$this->hostname] = explode(':', $this->id);
        }
    }

    public function __toString(): string
    {
        return $this->id;
    }

    /**
     * Starts a new scheduler worker. The worker will check for
     * delayed jobs every N seconds. Due to the check interval it
     * will ensure that jobs are ran no earlier than the scheduled
     * time, however, there are no guarantees that the job will
     * run at exactly the scheduled time.
     *
     * Locking is used to support multiple worker processes without
     * creating duplicate queue events.
     *
     * @param int         $interval    # of seconds to wait before checking for new delayed jobs
     * @param LockFactory $lockFactory Produces lock instances for orchestrating multiple worker processes
     * @param int         $lockTtl     # of seconds that the worker can hold onto the lock
     * @param bool        $verbose     Enables verbose logging
     */
    public function work(int $interval, LockFactory $lockFactory, int $lockTtl, bool $verbose = false): void
    {
        ResqueHelper::updateProcLine('Starting');
        $this->startup($verbose);

        // Check if this worker owns the lock before each
        // delayed event is processed because it is possible
        // that the previously acquired lock has expired and
        // now belongs to a different worker.
        $lock = $lockFactory->createLock('resque-scheduler', $lockTtl);
        Resque_Event::listen('beforeDelayedEnqueue', function () use ($lock) {
            if (!$lock->isAcquired()) {
                throw new LockConflictedException('This scheduler worker no longer has the lock');
            }
        });

        while (true) {
            if ($this->shutdown) {
                break;
            }

            // Attempt to acquire the master lock. If this
            // process crashes then that means another worker
            // cannot process scheduled jobs until it expires.
            if ($lock->isAcquired() || $lock->acquire()) {
                try {
                    ResqueHelper::updateProcLine('Processing Delayed Items');
                    $this->internalWorker->handleDelayedItems();
                } catch (LockConflictedException) {
                    // go back into waiting if we lose the lock
                }
            }

            ResqueHelper::updateProcLine('Waiting');
            sleep($interval);
        }

        $this->unregisterWorker($this->id);
    }

    /**
     * Perform necessary actions to start a worker.
     */
    private function startup(bool $verbose): void
    {
        $this->registerSignalHandlers();
        $this->pruneDeadWorkers();
        $this->registerWorker();

        $this->internalWorker = new ResqueScheduler_Worker();
        if ($verbose) {
            $this->internalWorker->logLevel = ResqueScheduler_Worker::LOG_VERBOSE;
        } else {
            $this->internalWorker->logLevel = ResqueScheduler_Worker::LOG_NONE;
        }
    }

    /**
     * Register this worker in Redis.
     */
    private function registerWorker(): void
    {
        $redis = ResqueHelper::redis();
        $redis->sadd(self::SCHEDULER_WORKER_KEY, $this->id); /* @phpstan-ignore-line */
        $redis->set('worker:'.$this->id.':started', date('D M d H:i:s T Y')); /* @phpstan-ignore-line */
    }

    /**
     * Unregisters a worker in Redis.
     */
    private function unregisterWorker(string $id): void
    {
        $redis = ResqueHelper::redis();
        $redis->srem(self::SCHEDULER_WORKER_KEY, $id); /* @phpstan-ignore-line */
        $redis->del('worker:'.$id); /* @phpstan-ignore-line */
        $redis->del('worker:'.$id.':started'); /* @phpstan-ignore-line */
    }

    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM: Shutdown immediately and stop processing jobs.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown immediately and stop processing jobs.
     */
    private function registerSignalHandlers(): void
    {
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGQUIT, [$this, 'shutdown']);
    }

    /**
     * Marks the worker to shutdown in the next loop iteration.
     */
    private function shutdown(): void
    {
        $this->shutdown = true;
    }

    /**
     * Look for any workers which should be running on this server and if
     * they're not, remove them from Redis.
     *
     * This is a form of garbage collection to handle cases where the
     * server may have been killed and the Resque workers did not die gracefully
     * and therefore leave state information in Redis.
     */
    private function pruneDeadWorkers(): void
    {
        $workerPids = $this->workerPids();
        foreach (ResqueSchedulerWorker::all() as $worker) {
            [$host, $pid] = explode(':', (string) $worker, 2);
            if ($host != $this->hostname || in_array($pid, $workerPids) || $pid == getmypid()) {
                continue;
            }
            $this->unregisterWorker($worker);
        }
    }

    /**
     * Return an array of process IDs for all of the Resque workers currently
     * running on this machine.
     *
     * @return array array of Resque worker process IDs
     */
    private function workerPids(): array
    {
        $pids = [];
        exec('ps -A -o pid,command | grep [r]esque-scheduler', $cmdOutput);
        foreach ($cmdOutput as $line) {
            [$pid] = explode(' ', trim($line), 2);
            $pids[] = $pid;
        }

        return $pids;
    }

    /**
     * Return all scheduler workers known to Resque as instantiated instances.
     *
     * @return self[]
     */
    public static function all(): array
    {
        return array_map(
            fn ($id) => new self($id),
            ResqueHelper::redis()->smembers(self::SCHEDULER_WORKER_KEY)); /* @phpstan-ignore-line */
    }
}
