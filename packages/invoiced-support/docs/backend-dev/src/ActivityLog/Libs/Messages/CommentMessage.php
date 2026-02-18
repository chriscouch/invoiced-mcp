<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;

class CommentMessage extends BaseMessage
{
    protected function estimateCommented(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' commented on '),
            $this->estimate(),
        ];
    }

    protected function invoiceCommented(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' commented on '),
            $this->invoice(),
        ];
    }

    protected function creditNoteCommented(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' commented on '),
            $this->creditNote(),
        ];
    }
}
