<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;

class PaymentSourceMessage extends BaseMessage
{
    protected function paymentSourceCreated(): array
    {
        return [
            $this->paymentSource($this->object),
            new AttributedString(' payment method added for '),
            $this->customer(),
        ];
    }

    protected function paymentSourceUpdated(): array
    {
        if (isset($this->previous['verified']) && !$this->previous['verified']) {
            return [
                $this->paymentSource($this->object),
                new AttributedString(' payment method was verified for '),
                $this->customer(),
            ];
        }

        return [
            $this->paymentSource($this->object),
            new AttributedString(' payment method updated for '),
            $this->customer(),
        ];
    }

    protected function paymentSourceDeleted(): array
    {
        return [
            $this->paymentSource($this->object),
            new AttributedString(' payment method removed for '),
            $this->customer(),
        ];
    }
}
