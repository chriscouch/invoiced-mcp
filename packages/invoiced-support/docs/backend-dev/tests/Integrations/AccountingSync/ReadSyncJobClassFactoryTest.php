<?php

namespace App\Tests\Integrations\AccountingSync;

use App\EntryPoint\QueueJob\BusinessCentralSyncJob;
use App\EntryPoint\QueueJob\FreshBooksSyncJob;
use App\EntryPoint\QueueJob\IntacctSyncJob;
use App\EntryPoint\QueueJob\NetSuiteSyncJob;
use App\EntryPoint\QueueJob\QuickBooksSyncJob;
use App\EntryPoint\QueueJob\SageAccountingSyncJob;
use App\EntryPoint\QueueJob\XeroSyncJob;
use App\Integrations\AccountingSync\ReadSync\ReadSyncJobClassFactory;
use App\Integrations\Enums\IntegrationType;
use App\Tests\AppTestCase;

class ReadSyncJobClassFactoryTest extends AppTestCase
{
    /**
     * @dataProvider provideJobs
     */
    public function testGet(IntegrationType $integration, string $class): void
    {
        $this->assertEquals($class, ReadSyncJobClassFactory::get($integration));
    }

    public function provideJobs(): array
    {
        return [
            [IntegrationType::BusinessCentral, BusinessCentralSyncJob::class],
            [IntegrationType::FreshBooks, FreshBooksSyncJob::class],
            [IntegrationType::Intacct, IntacctSyncJob::class],
            [IntegrationType::NetSuite, NetSuiteSyncJob::class],
            [IntegrationType::QuickBooksOnline, QuickBooksSyncJob::class],
            [IntegrationType::SageAccounting, SageAccountingSyncJob::class],
            [IntegrationType::Xero, XeroSyncJob::class],
        ];
    }
}
