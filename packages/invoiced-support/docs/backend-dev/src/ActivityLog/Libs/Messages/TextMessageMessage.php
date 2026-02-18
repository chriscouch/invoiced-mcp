<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class TextMessageMessage extends BaseMessage
{
    protected function textMessageSent(): array
    {
        // try to guess the type of document that was sent
        $objectType = $this->customer ? 'customer' : null;
        $objectId = $this->customer ? $this->customer->id() : null;
        if ($this->invoice) {
            $objectType = 'invoice';
            $objectId = $this->invoice->id();
        }

        return [
            $this->customer('customerName'),
            new AttributedString(' was sent a '),
            new AttributedObject('text_message', 'Text Message', [
                'object' => $objectType,
                'object_id' => $objectId,
            ]),
        ];
    }
}
