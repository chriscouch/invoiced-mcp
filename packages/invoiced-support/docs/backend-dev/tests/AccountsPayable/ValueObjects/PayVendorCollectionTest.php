<?php

namespace App\Tests\AccountsPayable\ValueObjects;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\AccountsPayable\ValueObjects\PayVendorCollection;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;

class PayVendorCollectionTest extends AppTestCase
{
    public function testBuild(): void
    {
        $collection = new PayVendorCollection();
        $collection->add($this->makeBillItem(1, 100));
        $collection->add($this->makeBillItem(2, 100));
        $collection->add($this->makeBillItem(3, 100));
        $collection->add($this->makeBillItem(1, 200));
        $collection->add($this->makeBillItem(1, 300));
        $collection->add($this->makeBillItem(1, 400));

        $payments = $collection->all();
        $this->assertCount(3, $payments);
        $this->assertEquals(new Money('usd', 100000), $payments[1]->getAmount());
        $this->assertEquals(1, $payments[1]->vendor->id);
        $this->assertCount(4, $payments[1]->getBatchBills());
        $this->assertEquals(new Money('usd', 10000), $payments[2]->getAmount());
        $this->assertEquals(2, $payments[2]->vendor->id);
        $this->assertCount(1, $payments[2]->getBatchBills());
        $this->assertEquals(new Money('usd', 10000), $payments[3]->getAmount());
        $this->assertEquals(3, $payments[3]->vendor->id);
        $this->assertCount(1, $payments[3]->getBatchBills());
    }

    private function makeBillItem(int $vendorId, float $amount): VendorPaymentBatchBill
    {
        $vendor = new Vendor(['id' => $vendorId]);
        $bill = new Bill(['currency' => 'usd', 'vendor_id' => $vendorId]);
        $paymentItem = new VendorPaymentBatchBill();
        $paymentItem->vendor = $vendor;
        $paymentItem->bill = $bill;
        $paymentItem->amount = $amount;

        return $paymentItem;
    }
}
