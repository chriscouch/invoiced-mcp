<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\EntryPoint\QueueJob\BusinessCentralSyncJob;
use App\EntryPoint\QueueJob\FreshBooksSyncJob;
use App\EntryPoint\QueueJob\IntacctSyncJob;
use App\EntryPoint\QueueJob\NetSuiteSyncJob;
use App\EntryPoint\QueueJob\QuickBooksSyncJob;
use App\EntryPoint\QueueJob\SageAccountingSyncJob;
use App\EntryPoint\QueueJob\XeroSyncJob;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;

class ReadSyncJobClassFactory
{
    /**
     * @throws IntegrationException
     */
    public static function get(IntegrationType $type): string
    {
        return match ($type) {
            IntegrationType::BusinessCentral => BusinessCentralSyncJob::class,
            IntegrationType::FreshBooks => FreshBooksSyncJob::class,
            IntegrationType::Intacct => IntacctSyncJob::class,
            IntegrationType::NetSuite => NetSuiteSyncJob::class,
            IntegrationType::QuickBooksOnline => QuickBooksSyncJob::class,
            IntegrationType::SageAccounting => SageAccountingSyncJob::class,
            IntegrationType::Xero => XeroSyncJob::class,
            default => throw new IntegrationException($type->toHumanName().' integration does not support read syncs'),
        };
    }
}
