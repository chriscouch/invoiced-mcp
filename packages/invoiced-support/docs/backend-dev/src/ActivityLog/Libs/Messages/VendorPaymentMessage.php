<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class VendorPaymentMessage extends BaseMessage
{
    protected function vendorPaymentCreated(): array
    {
        return [
            new AttributedString('Paid '),
            $this->vendorPayment(),
            new AttributedString(' to '),
            $this->vendor('vendor.name'),
        ];
    }

    protected function vendorPaymentUpdated(): array
    {
        return [
            new AttributedString('Payment to '),
            $this->vendor('vendor.name'),
            new AttributedString(' was updated'),
        ];
    }

    protected function vendorPaymentDeleted(): array
    {
        return [
            new AttributedString('Payment to '),
            $this->vendor('vendor.name'),
            new AttributedString(' was voided'),
        ];
    }

    private function vendorPayment(): AttributedObject
    {
        return new AttributedObject('vendor_payment', $this->moneyAmount(), array_value($this->associations, 'vendor_payment'));
    }
}
