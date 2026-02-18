<?php

namespace App\ActivityLog\Storage;

use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\ValueObjects\EventData;
use stdClass;

class NullStorage implements EventStorageInterface
{
    public function store(int $tenantId, int $eventId, EventData $data): void
    {
    }

    public function hydrateEvents(array $events): void
    {
        foreach ($events as $event) {
            $event->hydrateEventData(new EventData(new stdClass()));
        }
    }
}
