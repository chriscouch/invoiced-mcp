<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\VendorPayment;
use App\Core\Ledger\Exception\LedgerException;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use Carbon\CarbonImmutable;
use App\Core\Orm\Exception\ModelException;

class VoidVendorPayment extends AbstractSaveVendorPayment
{
    public function __construct(
        private readonly AccountsPayableLedger $accountsPayableLedger,
        private readonly EventSpool $eventSpool,
        BillStatusTransition $billStatusTransition,
    ) {
        parent::__construct($billStatusTransition);
    }

    /**
     * Voids the payment. This operation is irreversible.
     *
     * @throws ModelException
     */
    public function void(VendorPayment $payment): void
    {
        if ($payment->voided) {
            throw new ModelException('This payment has already been voided.');
        }

        // void the payment
        EventSpool::disablePush();
        $payment->voided = true;
        $payment->date_voided = CarbonImmutable::now();

        try {
            $payment->saveOrFail();
        } catch (ModelException $e) {
            EventSpool::enablePop();
            throw $e;
        }

        // create a deleted event
        $metadata = $payment->getEventObject();
        $associations = $payment->getEventAssociations();

        EventSpool::enablePop();
        $pendingEvent = new PendingDeleteEvent($payment, EventType::VendorPaymentDeleted, $metadata, $associations);
        $this->eventSpool->enqueue($pendingEvent);

        // make the ledger entries
        $ledger = $this->accountsPayableLedger->getLedger($payment->tenant());
        try {
            $this->accountsPayableLedger->syncPayment($ledger, $payment);
            $this->saveRelatedDocumentStatuses($payment);
        } catch (LedgerException $e) {
            throw new ModelException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
