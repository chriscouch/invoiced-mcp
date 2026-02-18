<?php

namespace App\Core\Queue\Interfaces;

/**
 * When added to a queue job class will add
 * a maximum concurrency limit.
 */
interface MaxConcurrencyInterface
{
    /**
     * Specifies the maximum number of instances of this
     * job that can be running at a time. The limit is
     * tracked by the identifier created with 'getConcurrencyKey()'.
     *
     * @param array $args Arguments passed to the job
     */
    public static function getMaxConcurrency(array $args): int;

    /**
     * Generates the semaphore identifier for tracking
     * the concurrency limit for this job.
     *
     * @param array $args Arguments passed to the job
     */
    public static function getConcurrencyKey(array $args): string;

    /**
     * Specifies the maximum expected job duration in seconds.
     *
     * @param array $args Arguments passed to the job
     */
    public static function getConcurrencyTtl(array $args): int;

    /**
     * Indicates whether the job should be delayed and retried
     * when the concurrency limit is reached. Most jobs should
     * be retried if the concurrency limit is reached. However,
     * some jobs that are opportunistic like an hourly sync
     * does not need to be delayed because it will already have
     * a future attempt scheduled. Otherwise if delayed these
     * kind of jobs can build up in the queue.
     */
    public static function delayAtConcurrencyLimit(): bool;
}
