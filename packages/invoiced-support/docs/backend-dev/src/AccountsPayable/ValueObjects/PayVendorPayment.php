<?php

namespace App\AccountsPayable\ValueObjects;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\Core\I18n\ValueObjects\Money;
use RuntimeException;

final class PayVendorPayment
{
    /** @var PayVendorItem[] */
    private array $items = [];
    /** @var VendorPaymentBatchBill[] */
    private array $batchBills = [];
    private Money $amount;

    public function __construct(
        public readonly Vendor $vendor,
    ) {
    }

    public function addBill(Bill $bill, Money $amount): void
    {
        if ($bill->vendor_id != $this->vendor->id) {
            throw new RuntimeException('Bill vendor mismatch');
        }

        $this->items[] = new PayVendorItem($bill, $amount);
        if (!isset($this->amount)) {
            $this->amount = $amount;
        } else {
            $this->amount = $this->amount->add($amount);
        }
    }

    public function addBatchBill(VendorPaymentBatchBill $batchBill): void
    {
        $this->batchBills[] = $batchBill;
        $amount = Money::fromDecimal($batchBill->bill->currency, $batchBill->amount);
        $this->addBill($batchBill->bill, $amount);
    }

    /**
     * @return PayVendorItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    /**
     * @return VendorPaymentBatchBill[]
     */
    public function getBatchBills(): array
    {
        return $this->batchBills;
    }
}
