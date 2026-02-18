<?php

namespace App\Core\Cron\Interfaces;

use App\Core\Cron\ValueObjects\Run;

interface CronJobInterface
{
    /**
     * Gets the name of this cron job. The cron job name should be
     * unique across all cron jobs.
     *
     * Allowed characters: lower case alphanumeric, _
     *
     * Examples:
     * - autopay
     * - bill_subscriptions
     * - late_fees
     * - quickbooks_syncs
     */
    public static function getName(): string;

    /**
     * Gets the duration in seconds of the lock for this cron job.
     * This would be the maximum amount of time before another
     * cron job of the same type to be able to start. The TTL
     * value should exceed the expected runtime of the cron job.
     */
    public static function getLockTtl(): int;

    /**
     * Executes the cron job.
     */
    public function execute(Run $run): void;
}
