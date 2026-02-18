<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\Interfaces\AttributedValueInterface;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class ChargeMessage extends BaseMessage
{
    protected function chargeFailed(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' payment for '),
            $this->charge(),
            new AttributedString(' failed'),
        ];
    }

    protected function chargePending(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' payment for '),
            $this->charge(),
            new AttributedString(' is pending'),
        ];
    }

    protected function chargeSucceeded(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' payment for '),
            $this->charge(),
            new AttributedString(' succeeded'),
        ];
    }

    private function charge(): AttributedValueInterface
    {
        $paymentId = array_value($this->associations, 'payment');
        if (!$paymentId) {
            return new AttributedString($this->moneyAmount());
        }

        return new AttributedObject('payment', $this->moneyAmount(), array_value($this->associations, 'payment'));
    }
}
