<?php

namespace App\EntryPoint\CronJob;

use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use Countable;
use Symfony\Component\String\UnicodeString;

/**
 * This is a base class for cron jobs that work like
 * a task queue. These types of cron jobs follow a
 * similar pattern of getting all tasks that must
 * be performed, tracking the count on statsd, and
 * then processing each task.
 */
abstract class AbstractTaskQueueCronJob implements CronJobInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    private int $taskCount = 0;

    /**
     * Gets a list of tasks that must be executed
     * when performing this cron job.
     */
    abstract public function getTasks(): iterable;

    /**
     * Runs the job for a task.
     *
     * @return bool true if the task is executed
     */
    abstract public function runTask(mixed $task): bool;

    public static function getName(): string
    {
        $class = static::class;
        $class = substr($class, strrpos($class, '\\') + 1);

        return (new UnicodeString($class))->snake()->toString();
    }

    public function execute(Run $run): void
    {
        $this->taskCount = 0;
        $tasks = $this->getTasks();

        // If the result returned by tasks is countable
        // then we can go ahead and get the task count.
        // Not every iterable is guaranteed to be countable.
        if (is_array($tasks) || $tasks instanceof Countable) {
            $this->taskCount = count($tasks);
        }

        $totalCount = $this->getTaskCount();
        $run->writeOutput('Tasks in queue: '.number_format($totalCount));
        $this->statsd->gauge('cron.task_queue_size', $totalCount, 1, ['cron_job' => static::getName()]);

        $n = 0;
        foreach ($tasks as $task) {
            if ($this->runTask($task)) {
                ++$n;
            }
        }

        $run->writeOutput('Processed tasks: '.number_format($n));
        $this->statsd->updateStats('cron.processed_task', $n, 1.0, ['cron_job' => static::getName()]);
    }

    /**
     * Gets the total count of tasks in the queue
     * that this cron job should perform. The task
     * count does not have to equal the number of
     * tasks returned by getTasks(). This function
     * will always be called after getTasks().
     */
    public function getTaskCount(): int
    {
        return $this->taskCount;
    }
}
