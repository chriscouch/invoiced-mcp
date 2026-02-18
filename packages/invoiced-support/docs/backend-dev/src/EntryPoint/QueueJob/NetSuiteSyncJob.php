<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Queue\AbstractResqueJob;
use App\Integrations\NetSuite\Libs\NetSuiteRetry;

class NetSuiteSyncJob extends AbstractResqueJob
{
    public function __construct(private NetSuiteRetry $retry)
    {
    }

    public function perform(): void
    {
        // Retries are the only type of sync supported by the NetSuite integration
        $this->retry->retry($this->args);
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'accounting_read_sync:'.$args['tenant_id'];
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyTtl(array $args): int
    {
        // Maximum is 24 hours. If a sync ever exceeds 24 hours
        // then it would result in concurrent syncs operating.
        return 86400;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        // If this job is already running for a tenant then there
        // is no need to retry this one because it is automatically
        // retried every hour.
        return false;
    }
}
