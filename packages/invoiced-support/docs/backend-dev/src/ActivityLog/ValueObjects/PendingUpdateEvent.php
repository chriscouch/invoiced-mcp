<?php

namespace App\ActivityLog\ValueObjects;

/**
 * This class represents an update event pending write
 * to the database.
 */
class PendingUpdateEvent extends PendingEvent
{
    public function isUpdate(): bool
    {
        return true;
    }
}
