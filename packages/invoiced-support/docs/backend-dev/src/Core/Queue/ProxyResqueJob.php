<?php

namespace App\Core\Queue;

use AllowDynamicProperties;
use Psr\Container\ContainerInterface;

/**
 * Using the proxy pattern, this class proxies for the job
 * we actually want to execute in Resque. The reason we cannot
 * queue the job class directly is because the way that Resque
 * initializes job classes is hard-coded. Using this proxy class we
 * can load jobs from the Symfony service container and benefit
 * from dependency injection.
 */
#[AllowDynamicProperties]
class ProxyResqueJob
{
    private static ContainerInterface $jobLocator;

    public array $args = [];
    public string $queue = '';

    public function perform(): void
    {
        $job = self::$jobLocator->get($this->args['_job_class']);
        $job->args = $this->args;
        $job->queue = $this->queue;
        $job->perform();
    }

    public static function setJobLocator(ContainerInterface $jobLocator): void
    {
        self::$jobLocator = $jobLocator;
    }
}
