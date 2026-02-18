<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class VendorAdjustmentMessage extends BaseMessage
{
    protected function vendorAdjustmentCreated(): array
    {
        return [
            new AttributedString('Adjustment for '),
            $this->vendor(),
            new AttributedString(' was created: '),
            $this->vendorAdjustment(),
        ];
    }

    protected function vendorAdjustmentDeleted(): array
    {
        return [
            new AttributedString('Adjustment for '),
            $this->vendor(),
            new AttributedString(' was voided'),
        ];
    }

    private function vendorAdjustment(): AttributedObject
    {
        return new AttributedObject('vendor_adjustment', $this->moneyAmount(), array_value($this->associations, 'vendor_adjustment'));
    }
}
