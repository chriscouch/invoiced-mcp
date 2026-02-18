<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\VendorAdjustment;
use App\Core\Ledger\Exception\LedgerException;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;

class VoidVendorAdjustment
{
    public function __construct(
        private AccountsPayableLedger $accountsPayableLedger,
        private EventSpool $eventSpool,
    ) {
    }

    /**
     * Voids the adjustment. This operation is irreversible.
     *
     * @throws ModelException
     */
    public function void(VendorAdjustment $adjustment): void
    {
        if ($adjustment->voided) {
            throw new ModelException('This adjustment has already been voided.');
        }

        // void the adjustment
        EventSpool::disablePush();
        $adjustment->voided = true;
        $adjustment->date_voided = CarbonImmutable::now();

        try {
            $adjustment->saveOrFail();
        } catch (ModelException $e) {
            EventSpool::enablePop();
            throw $e;
        }

        // create a deleted event
        $metadata = $adjustment->getEventObject();
        $associations = $adjustment->getEventAssociations();

        EventSpool::enablePop();
        $pendingEvent = new PendingDeleteEvent($adjustment, EventType::VendorAdjustmentDeleted, $metadata, $associations);
        $this->eventSpool->enqueue($pendingEvent);

        // make the ledger entries
        $ledger = $this->accountsPayableLedger->getLedger($adjustment->tenant());
        try {
            $this->accountsPayableLedger->syncAdjustment($ledger, $adjustment);
        } catch (LedgerException $e) {
            throw new ModelException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
