<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class CustomerMessage extends BaseMessage
{
    protected function customerCreated(): array
    {
        return [
            $this->customer(),
            new AttributedString(' was added as a new customer'),
        ];
    }

    protected function customerUpdated(): array
    {
        if (array_key_exists('payment_source', $this->previous)) {
            return $this->changedPaymentSource();
        }

        if (array_key_exists('autopay', $this->previous)) {
            return $this->changedAutoPay();
        }

        if (array_key_exists('notes', $this->previous)) {
            return $this->changedNotes();
        }

        return [
            new AttributedString('The profile for '),
            $this->customer(),
            new AttributedString(' was updated'),
        ];
    }

    private function changedPaymentSource(): array
    {
        $source = $this->object['payment_source'];
        if (!$source) {
            return [
                $this->customer(),
                new AttributedString(' no longer has a default payment method'),
            ];
        }

        return [
            $this->customer(),
            new AttributedString(' has a new default payment method: '),
            $this->paymentSource($source),
        ];
    }

    private function changedAutoPay(): array
    {
        if (!$this->previous['autopay']) {
            $action = ' enrolled in AutoPay';
        } else {
            $action = ' disabled AutoPay';
        }

        return [
            $this->customer(),
            new AttributedString($action),
        ];
    }

    private function changedNotes(): array
    {
        return [
            $this->customer(),
            new AttributedString(' notes were changed'),
        ];
    }

    protected function customerDeleted(): array
    {
        return [
            $this->customer(),
            new AttributedString(' was removed'),
        ];
    }

    protected function customerMerged(): array
    {
        $customerName = (string) array_value($this->object, 'original_customer.name');

        return [
            new AttributedObject('customer', $customerName, -1),
            new AttributedString(' was merged into '),
            $this->customer(),
        ];
    }
}
