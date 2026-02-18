<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\ChartMogul\ChartMogulSync;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;

class ChartMogulSyncJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    public function __construct(private ChartMogulSync $sync)
    {
    }

    public function perform(): void
    {
        /** @var ChartMogulAccount|null $account */
        $account = ChartMogulAccount::find($this->args['accountId']);
        if (!$account) {
            return;
        }

        $this->sync->run($account);
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'chartmogul_sync:'.$args['tenant_id'];
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
