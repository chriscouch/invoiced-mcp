<?php

namespace App\ActivityLog\ValueObjects;

/**
 * This class represents a create event pending write
 * to the database.
 */
class PendingCreateEvent extends PendingEvent
{
    public function isCreate(): bool
    {
        return true;
    }
}
