<?php

namespace App\Integrations\ChartMogul\Interfaces;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;

interface ChartMogulSyncInterface
{
    /**
     * Performs a sync with ChartMogul.
     *
     * @throws SyncException
     */
    public function sync(ChartMogulAccount $account): void;
}
