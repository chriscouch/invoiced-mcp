<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\Flywire\FlywireHelper;
use App\Integrations\Flywire\Interfaces\FlywireSyncInterface;
use App\PaymentProcessing\Models\MerchantAccount;

class FlywireSyncJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    /**
     * @param FlywireSyncInterface[] $syncs
     */
    public function __construct(
        private iterable $syncs,
    ) {
    }

    public function perform(): void
    {
        /** @var MerchantAccount|null $account */
        $account = MerchantAccount::find($this->args['merchantAccountId']);
        if (!$account) {
            return;
        }

        $portalCodes = FlywireHelper::getPortalCodes($account);
        if (!$portalCodes) {
            return;
        }

        // Check if performing a full sync or incremental sync.
        $company = $account->tenant();
        $fullSync = $company->features->has('flywire_full_sync');

        foreach ($this->syncs as $sync) {
            $sync->sync($account, $portalCodes, $fullSync);
        }

        // Once the full sync is performed we return back to incremental sync.
        if ($fullSync) {
            $company->features->remove('flywire_full_sync');
        }
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 5;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'flywire_sync';
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 1800;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return false;
    }
}
