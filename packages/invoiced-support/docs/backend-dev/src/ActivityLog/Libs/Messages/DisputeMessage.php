<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class DisputeMessage extends BaseMessage
{
    protected function disputeCreated(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' opened a dispute for '),
            $this->dispute(),
        ];
    }

    private function dispute(): AttributedObject
    {
        return new AttributedObject('payment', $this->moneyAmount(), array_value($this->associations, 'payment'));
    }
}
