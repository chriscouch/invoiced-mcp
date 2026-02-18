<?php

namespace App\AccountsReceivable\Operations;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Models\PaymentLink;
use App\Core\Orm\Exception\ModelException;
use App\ActivityLog\Libs\EventSpool;

class DeletePaymentLink
{
    /**
     * @throws ModelException
     */
    public function delete(PaymentLink $paymentLink): void
    {
        if (PaymentLinkStatus::Active != $paymentLink->status) {
            throw new ModelException('This link cannot be deleted');
        }

        EventSpool::disablePush();
        $paymentLink->status = PaymentLinkStatus::Deleted;
        $paymentLink->saveOrFail();
        EventSpool::enablePop();
        $paymentLink->deleteOrFail();
    }
}
