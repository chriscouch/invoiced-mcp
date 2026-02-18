<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;

class DocumentViewMessage extends BaseMessage
{
    protected function invoiceViewed(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' viewed '),
            $this->invoice(),
        ];
    }

    protected function estimateViewed(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' viewed '),
            $this->estimate(),
        ];
    }

    protected function creditNoteViewed(): array
    {
        return [
            $this->customer('customerName'),
            new AttributedString(' viewed '),
            $this->creditNote(),
        ];
    }
}
