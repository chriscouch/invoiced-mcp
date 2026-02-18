<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * This class is used to test the max concurrency feature.
 */
class ConcurrencyTestJob extends AbstractResqueJob implements LoggerAwareInterface, MaxConcurrencyInterface
{
    use LoggerAwareTrait;

    public static function getConcurrencyKey(array $args): string
    {
        return 'concurrency_test';
    }

    public static function getMaxConcurrency(array $args): int
    {
        return $args['limit'] ?? 1;
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return $args['duration'] ?? 5;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }

    public function perform(): void
    {
        $id = random_int(0, 1000);
        $limit = self::getMaxConcurrency($this->args);
        $this->logger->warning('Starting concurrency test job with limit: '.$limit.' Unique ID: '.$id);
        $sleepFor = $this->args['duration'] ?? 5;
        sleep($sleepFor);
        $this->logger->warning('Finished concurrency test: '.$id);
    }
}
