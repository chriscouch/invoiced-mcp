<?php

namespace App\ActivityLog\Libs\Messages;

use App\ActivityLog\ValueObjects\AttributedString;

class VendorMessage extends BaseMessage
{
    protected function vendorCreated(): array
    {
        return [
            $this->vendor(),
            new AttributedString(' was added as a new vendor'),
        ];
    }

    protected function vendorUpdated(): array
    {
        return [
            new AttributedString('The profile for '),
            $this->vendor(),
            new AttributedString(' was updated'),
        ];
    }

    protected function vendorDeleted(): array
    {
        return [
            $this->vendor(),
            new AttributedString(' was removed'),
        ];
    }
}
