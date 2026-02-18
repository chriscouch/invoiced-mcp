<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;

class PaymentPlanMessage extends BaseMessage
{
    protected function paymentPlanCreated(): array
    {
        return [
            new AttributedString('Payment plan for '),
            $this->customer('customerName'),
            new AttributedString(' was created'),
        ];
    }

    protected function paymentPlanUpdated(): array
    {
        return [
            new AttributedString('Payment plan for '),
            $this->customer('customerName'),
            new AttributedString(' was updated'),
        ];
    }

    protected function paymentPlanDeleted(): array
    {
        return [
            new AttributedString('Payment plan for '),
            $this->customer('customerName'),
            new AttributedString(' was deleted'),
        ];
    }
}
