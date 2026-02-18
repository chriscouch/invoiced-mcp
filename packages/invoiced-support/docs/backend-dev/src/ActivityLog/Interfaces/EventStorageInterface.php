<?php

namespace App\ActivityLog\Interfaces;

use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\EventData;

interface EventStorageInterface
{
    /**
     * Saves the object data for an event.
     */
    public function store(int $tenantId, int $eventId, EventData $data): void;

    /**
     * Retrieves and hydrates the given event models.
     *
     * @param Event[] $events
     */
    public function hydrateEvents(array $events): void;
}
