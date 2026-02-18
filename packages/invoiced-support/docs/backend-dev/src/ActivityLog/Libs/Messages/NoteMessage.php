<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;

class NoteMessage extends BaseMessage
{
    protected function noteCreated(): array
    {
        return [
            new AttributedString('Note for '),
            $this->customer('customerName'),
            new AttributedString(' was created'),
        ];
    }

    protected function noteUpdated(): array
    {
        return [
            new AttributedString('Note for '),
            $this->customer('customerName'),
            new AttributedString(' was updated'),
        ];
    }

    protected function noteDeleted(): array
    {
        return [
            new AttributedString('Note for '),
            $this->customer('customerName'),
            new AttributedString(' was deleted'),
        ];
    }
}
