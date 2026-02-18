<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Exception\AccountsPayablePaymentException;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\AccountsPayable\ValueObjects\PayVendorCollection;
use App\Core\Utils\ModelUtility;

class PayVendorPaymentBatch
{
    public function __construct(
        private readonly PayVendor $payVendor,
    ) {
    }

    public function pay(VendorPaymentBatch $paymentBatch): void
    {
        // Check if the batch payment has already been processed
        if (in_array($paymentBatch->status, [VendorBatchPaymentStatus::Finished, VendorBatchPaymentStatus::Voided])) {
            return;
        }

        // Convert the payment batch items into a single payment per vendor
        $query = VendorPaymentBatchBill::queryWithoutMultitenancyUnsafe()
            ->where('vendor_payment_batch_id', $paymentBatch);
        $bills = ModelUtility::getAllModelsGenerator($query);
        $collection = new PayVendorCollection();
        foreach ($bills as $bill) {
            $collection->add($bill);
        }

        // Process each payment in the collection.
        // If an individual payment fails then mark
        // the failure and move on to the next payment.
        $checkNumber = (int) $paymentBatch->initial_check_number;
        foreach ($collection->all() as $item) {
            try {
                $options = [
                    'payment_batch' => $paymentBatch,
                    'bank_account' => $paymentBatch->bank_account,
                    'card' => $paymentBatch->card,
                    'check_number' => $checkNumber,
                ];
                $this->payVendor->pay($paymentBatch->payment_method, $item, $options);

                ++$checkNumber;
            } catch (AccountsPayablePaymentException $e) {
                // Mark each bill in the item as failed
                foreach ($item->getBatchBills() as $batchBill) {
                    $batchBill->error = $e->getMessage();
                    $batchBill->saveOrFail();
                }
            }
        }

        $paymentBatch->status = VendorBatchPaymentStatus::Finished;
        $paymentBatch->saveOrFail();
    }
}
