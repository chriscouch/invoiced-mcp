<?php

namespace App\PaymentProcessing\Operations;

use App\Core\Orm\Exception\ModelException;
use App\Core\Utils\ModelUtility;
use App\PaymentProcessing\Enums\CustomerBatchPaymentStatus;
use App\PaymentProcessing\Models\CustomerPaymentBatch;
use App\PaymentProcessing\Models\CustomerPaymentBatchItem;

class VoidCustomerPaymentBatch
{
    /**
     * Voids the payment batch. This operation is irreversible.
     *
     * @throws ModelException
     */
    public function void(CustomerPaymentBatch $paymentBatch): void
    {
        if (CustomerBatchPaymentStatus::Processing == $paymentBatch->status) {
            throw new ModelException('This payment batch cannot be voided because it is currently processing.');
        }

        if (CustomerBatchPaymentStatus::Voided == $paymentBatch->status) {
            throw new ModelException('This payment batch has already been voided.');
        }

        if (CustomerBatchPaymentStatus::Finished == $paymentBatch->status) {
            throw new ModelException('This payment batch cannot be voided because it has already been completed.');
        }

        // void the batch
        $paymentBatch->status = CustomerBatchPaymentStatus::Voided;
        $paymentBatch->total = 0;
        $paymentBatch->saveOrFail();

        // delete each item in the batch so they may be reused
        $query = CustomerPaymentBatchItem::where('customer_payment_batch_id', $paymentBatch)
            ->with('charge');
        $batchItems = ModelUtility::getAllModels($query);
        foreach ($batchItems as $batchItem) {
            $batchItem->deleteOrFail();
        }
    }
}
