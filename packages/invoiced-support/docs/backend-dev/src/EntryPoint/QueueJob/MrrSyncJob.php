<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\SubscriptionBilling\Metrics\MrrSync;
use Symfony\Component\Console\Output\NullOutput;

class MrrSyncJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    public function __construct(
        private MrrSync $mrrSync,
        private TenantContext $tenant,
    ) {
    }

    public function perform(): void
    {
        $company = $this->tenant->get();
        $this->mrrSync->sync($company, new NullOutput(), false);
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'mrr_sync:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 1800; // 30 minutes
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return false;
    }
}
