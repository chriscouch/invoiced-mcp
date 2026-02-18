<?php

namespace App\PaymentProcessing\Operations;

use App\PaymentProcessing\Enums\CustomerBatchPaymentStatus;
use App\PaymentProcessing\Exceptions\TransactionStatusException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\CustomerPaymentBatch;

class CompleteCustomerPaymentBatch
{
    public function __construct(
        private UpdateChargeStatus $updateChargeStatus,
    ) {
    }

    public function complete(CustomerPaymentBatch $paymentBatch): void
    {
        // Check if the batch payment has already been processed
        if (in_array($paymentBatch->status, [CustomerBatchPaymentStatus::Finished, CustomerBatchPaymentStatus::Voided])) {
            return;
        }

        $items = $paymentBatch->getItems();
        foreach ($items as $item) {
            try {
                if (Charge::SUCCEEDED !== $item->charge->status) {
                    $this->updateChargeStatus->saveStatus($item->charge, Charge::SUCCEEDED);
                }
            } catch (TransactionStatusException) {
                // ignore the error and continue processing the batch
            }
        }

        $paymentBatch->status = CustomerBatchPaymentStatus::Finished;
        $paymentBatch->saveOrFail();
    }
}
