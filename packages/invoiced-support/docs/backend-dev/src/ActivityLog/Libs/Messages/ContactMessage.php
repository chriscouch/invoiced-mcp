<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;

class ContactMessage extends BaseMessage
{
    protected function contactCreated(): array
    {
        $contactName = array_value($this->object, 'name');

        return [
            new AttributedString('Contact for '),
            $this->customer('customerName'),
            new AttributedString(' was created: '.$contactName),
        ];
    }

    protected function contactUpdated(): array
    {
        $contactName = array_value($this->object, 'name');

        return [
            new AttributedString('Contact for '),
            $this->customer('customerName'),
            new AttributedString(' was updated: '.$contactName),
        ];
    }

    protected function contactDeleted(): array
    {
        $contactName = array_value($this->object, 'name');

        return [
            new AttributedString('Contact for '),
            $this->customer('customerName'),
            new AttributedString(' was deleted: '.$contactName),
        ];
    }
}
