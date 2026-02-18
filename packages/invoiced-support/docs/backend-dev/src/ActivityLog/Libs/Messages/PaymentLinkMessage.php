<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class PaymentLinkMessage extends BaseMessage
{
    protected function paymentLinkCreated(): array
    {
        return [
            $this->paymentLink(),
            new AttributedString(' was created'),
        ];
    }

    protected function paymentLinkUpdated(): array
    {
        return [
            $this->paymentLink(),
            new AttributedString(' was updated'),
        ];
    }

    protected function paymentLinkDeleted(): array
    {
        return [
            $this->paymentLink(),
            new AttributedString(' was deleted'),
        ];
    }

    protected function paymentLinkCompleted(): array
    {
        return [
            $this->paymentLink(),
            new AttributedString(' was completed'),
        ];
    }

    private function paymentLink(): AttributedObject
    {
        // try to get the name from the document object
        $name = $this->object['name'] ?? 'Payment Link';

        // if all else fails, then use the generic deleted name
        if (empty(trim($name))) {
            $name = '[deleted]';
        }

        return new AttributedObject('payment_link', $name, $this->associations['payment_link'] ?? null);
    }
}
