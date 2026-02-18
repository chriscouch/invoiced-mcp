<?php

namespace App\Integrations\AccountingSync\ReadSync;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Interfaces\AccountingReaderInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingSyncStatus;
use App\Integrations\AccountingSync\ValueObjects\ReadQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;
use App\Core\Orm\Model;

class ReadSync
{
    /**
     * Syncs the objects that were updated in the accounting system
     * since the last sync. The sync will only use readers that
     * are enabled.
     *
     * @param AccountingReaderInterface[] $readers
     */
    public function syncOngoing(Model $account, AccountingSyncProfile $syncProfile, iterable $readers): void
    {
        // use the company's time zone for date stuff
        $syncProfile->tenant()->useTimezone();

        // Build the query for the sync
        $lastSynced = CarbonImmutable::createFromTimestamp($syncProfile->read_cursor ?? $syncProfile->created_at, new CarbonTimeZone('UTC'));
        $startDate = $syncProfile->invoice_start_date ? CarbonImmutable::createFromTimestamp($syncProfile->invoice_start_date)->setTime(0, 0) : null;
        $query = new ReadQuery($lastSynced, $startDate, null);

        // Determine readers to use
        $enabledReaders = [];
        foreach ($readers as $reader) {
            if ($reader->isEnabled($syncProfile)) {
                $enabledReaders[] = $reader;
            }
        }

        $this->performSync($account, $syncProfile, $enabledReaders, $query, true);
    }

    /**
     * Syncs the objects that existed prior to installing the integration
     * using the given query. The sync will run for all the readers provided.
     * This does not check if the reader is enabled.
     *
     * @param AccountingReaderInterface[] $readers
     */
    public function syncHistorical(Model $account, AccountingSyncProfile $syncProfile, array $readers, ReadQuery $query): void
    {
        // use the company's time zone for date stuff
        $syncProfile->tenant()->useTimezone();

        $this->performSync($account, $syncProfile, $readers, $query, false);
    }

    /**
     * @param AccountingReaderInterface[] $readers
     */
    private function performSync(Model $account, AccountingSyncProfile $syncProfile, array $readers, ReadQuery $query, bool $setReadCursor): void
    {
        $startTime = CarbonImmutable::now()->unix();
        AccountingSyncStatus::beingSync($syncProfile->getIntegrationType(), $query);

        try {
            // Sync the objects matching the query for every enabled reader.
            foreach ($readers as $reader) {
                $reader->syncAll($account, $syncProfile, $query);
            }

            // Advance the read cursor to the beginning of the
            // sync in order for future syncs to not have to
            // reprocess already synced data.
            if ($setReadCursor) {
                $syncProfile->read_cursor = $startTime;
            }
        } catch (SyncException) {
            // This exception is not logged because it should have already
            // been recorded as a user-visible sync error by the reader.
        }

        $syncProfile->last_synced = $startTime;
        $syncProfile->saveOrFail();

        AccountingSyncStatus::finishSync($syncProfile->getIntegrationType());
    }
}
