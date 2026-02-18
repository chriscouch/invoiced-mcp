<?php

namespace App\Core\Queue;

use Resque;
use Resque_Redis;

class ResqueHelper
{
    /**
     * On supported systems (with the PECL proctitle module installed), update
     * the name of the currently running process to indicate the current state
     * of a worker.
     */
    public static function updateProcLine(string $status): void
    {
        $processTitle = 'resque-scheduler-'.Resque::VERSION.': '.$status;
        if (function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title($processTitle);
        }
    }

    /**
     * Gets the global Resque redis instance.
     */
    public static function redis(): Resque_Redis
    {
        return Resque::redis();
    }

    /**
     * Return the start date of a worker.
     *
     * @param string $worker Name of the worker
     *
     * @return string ISO-8601 formatted date
     */
    public static function getWorkerStartDate(string $worker): string
    {
        return self::redis()->get('worker:'.$worker.':started'); /* @phpstan-ignore-line */
    }
}
