<?php

namespace App\EntryPoint\QueueJob;

use App\Integrations\AccountingSync\ReadSync\AbstractReadSyncQueueJob;
use App\Integrations\Enums\IntegrationType;

class QuickBooksSyncJob extends AbstractReadSyncQueueJob
{
    protected function getIntegrationType(): IntegrationType
    {
        return IntegrationType::QuickBooksOnline;
    }
}
