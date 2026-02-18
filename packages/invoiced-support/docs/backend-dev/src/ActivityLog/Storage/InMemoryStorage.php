<?php

namespace App\ActivityLog\Storage;

use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\ValueObjects\EventData;
use stdClass;

class InMemoryStorage implements EventStorageInterface
{
    private array $data = [];

    public function store(int $tenantId, int $eventId, EventData $data): void
    {
        $this->data[$tenantId.'/'.$eventId] = $data;
    }

    public function hydrateEvents(array $events): void
    {
        foreach ($events as $event) {
            $event->hydrateEventData($this->data[$event->tenant_id.'/'.$event->id] ?? new EventData(new stdClass()));
        }
    }
}
