<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\Core\Utils\ModelUtility;
use App\Core\Orm\Exception\ModelException;

class VoidVendorPaymentBatch
{
    public function __construct(
        private VoidVendorPayment $voidVendorPayment,
    ) {
    }

    /**
     * Voids the payment batch. This operation is irreversible.
     *
     * @throws ModelException
     */
    public function void(VendorPaymentBatch $paymentBatch): void
    {
        if (VendorBatchPaymentStatus::Processing == $paymentBatch->status) {
            throw new ModelException('This payment batch cannot be voided because it is currently processing.');
        }

        if (VendorBatchPaymentStatus::Voided == $paymentBatch->status) {
            throw new ModelException('This payment batch has already been voided.');
        }

        if (VendorBatchPaymentStatus::Finished == $paymentBatch->status) {
            throw new ModelException('This payment batch cannot be voided because it has already been completed.');
        }

        // void the batch
        $paymentBatch->status = VendorBatchPaymentStatus::Voided;
        $paymentBatch->saveOrFail();

        // void each payment created by the batch
        $query = VendorPayment::where('vendor_payment_batch_id', $paymentBatch)
            ->where('voided', false);
        $payments = ModelUtility::getAllModels($query);
        foreach ($payments as $payment) {
            $this->voidVendorPayment->void($payment);
        }
    }
}
