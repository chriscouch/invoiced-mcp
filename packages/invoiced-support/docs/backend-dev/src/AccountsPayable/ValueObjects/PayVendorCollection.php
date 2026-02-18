<?php

namespace App\AccountsPayable\ValueObjects;

use App\AccountsPayable\Models\VendorPaymentBatchBill;

final class PayVendorCollection
{
    /** @var PayVendorPayment[] */
    private array $collection = [];

    public function add(VendorPaymentBatchBill $bill): void
    {
        $vendorId = $bill->vendor_id;
        if (!isset($this->collection[$vendorId])) {
            $this->collection[$vendorId] = new PayVendorPayment($bill->vendor);
        }

        $this->collection[$vendorId]->addBatchBill($bill);
    }

    /**
     * @return PayVendorPayment[]
     */
    public function all(): array
    {
        return $this->collection;
    }
}
