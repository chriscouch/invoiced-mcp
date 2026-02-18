<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class RefundMessage extends BaseMessage
{
    protected function refundCreated(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' was refunded '),
            $this->refund(),
        ];
    }

    private function refund(): AttributedObject
    {
        return new AttributedObject('payment', $this->moneyAmount(), array_value($this->associations, 'payment'));
    }
}
