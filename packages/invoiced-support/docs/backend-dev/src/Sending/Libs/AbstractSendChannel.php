<?php

namespace App\Sending\Libs;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Sending\Interfaces\SendChannelInterface;
use App\Sending\Models\ScheduledSend;

abstract class AbstractSendChannel implements SendChannelInterface
{
    /**
     * Returns an InvoiceDelivery object associated w/ a scheduled send provided the send
     * was Invoice chasing initiated.
     */
    protected function getInvoiceDelivery(ScheduledSend $scheduledSend): ?InvoiceDelivery
    {
        if (!$this->isChasingInitiated($scheduledSend)) {
            return null;
        }

        $deliveryId = explode(':', (string) $scheduledSend->reference)[1];

        return InvoiceDelivery::where('id', $deliveryId)->oneOrNull();
    }

    /**
     * Returns whether a scheduled send was initiated via a chasing schedule
     * from its reference.
     */
    private function isChasingInitiated(ScheduledSend $scheduledSend): bool
    {
        $parts = explode(':', (string) $scheduledSend->reference);
        if (3 != count($parts) || 'delivery' != $parts[0] || !is_numeric($parts[1])) {
            return false;
        }

        return true;
    }
}
