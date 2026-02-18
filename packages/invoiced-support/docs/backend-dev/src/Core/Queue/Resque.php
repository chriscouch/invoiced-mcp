<?php

namespace App\Core\Queue;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Resque_Worker;
use RuntimeException;

class Resque implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const INTERVAL = 5;

    private Resque_Worker $worker;

    /**
     * Starts the resque worker. Only one worker
     * can be started at a time within this process.
     */
    public function startWorker(string $queue): void
    {
        if (isset($this->worker)) {
            throw new RuntimeException('Worker has already started');
        }

        $queues = explode(',', $queue);
        $this->worker = new Resque_Worker($queues);
        $this->worker->setLogger($this->logger);
        $this->logger->log(LogLevel::NOTICE, 'Starting worker {worker}', ['worker' => $this->worker]);
        $this->worker->work(self::INTERVAL, false);
    }

    /**
     * Gracefully stops the resque worker.
     */
    public function stopWorker(): void
    {
        if (!isset($this->worker)) {
            throw new RuntimeException('Worker has not been started yet');
        }

        $this->worker->shutdown();
    }
}
