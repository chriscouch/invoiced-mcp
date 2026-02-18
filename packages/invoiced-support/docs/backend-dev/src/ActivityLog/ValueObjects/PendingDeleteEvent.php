<?php

namespace App\ActivityLog\ValueObjects;

/**
 * This class represents a delete event pending write
 * to the database.
 */
class PendingDeleteEvent extends PendingEvent
{
    public function isDelete(): bool
    {
        return true;
    }
}
