<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class LineItemMessage extends BaseMessage
{
    protected function lineItemCreated(): array
    {
        return [
            new AttributedString('New '),
            new AttributedObject('line_item', $this->moneyAmount(), array_value($this->associations, 'line_item')),
            new AttributedString(' pending line item for '),
            $this->customer('customerName'),
        ];
    }

    protected function lineItemUpdated(): array
    {
        return [
            new AttributedObject('line_item', $this->moneyAmount(), array_value($this->associations, 'line_item')),
            new AttributedString(' pending line item for '),
            $this->customer('customerName'),
            new AttributedString(' was updated'),
        ];
    }

    protected function lineItemDeleted(): array
    {
        return [
            new AttributedObject('line_item', $this->moneyAmount(), array_value($this->associations, 'line_item')),
            new AttributedString(' pending line item for '),
            $this->customer('customerName'),
            new AttributedString(' was deleted'),
        ];
    }
}
