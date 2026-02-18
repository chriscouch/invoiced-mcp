<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class LetterMessage extends BaseMessage
{
    protected function letterSent(): array
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
            new AttributedString(' was mailed a '),
            new AttributedObject('letter', 'Letter', [
                'object' => $objectType,
                'object_id' => $objectId,
            ]),
        ];
    }
}
