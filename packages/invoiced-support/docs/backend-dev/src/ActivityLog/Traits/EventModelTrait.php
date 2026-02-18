<?php

namespace App\ActivityLog\Traits;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventModelListener;

/**
 * This trait will implement common event object methods
 * for models. It will also install listeners to automatically
 * emit events based on model CRUD operations.
 */
trait EventModelTrait
{
    use EventObjectTrait;

    private ?EventType $updatedEventType = null;

    /**
     * Installs the event model listener.
     */
    protected function autoInitializeEventModel(): void
    {
        EventModelListener::add($this);
    }

    //
    // Getters
    //

    /**
     * Gets the event name for a create event.
     */
    public function getCreatedEventType(): ?EventType
    {
        $k = $this::modelName().'Created';
        foreach (EventType::cases() as $eventType) {
            if ($eventType->name == $k) {
                return $eventType;
            }
        }

        return null;
    }

    /**
     * Gets the event name for an update event.
     */
    public function getUpdatedEventType(): ?EventType
    {
        if ($eventType = $this->updatedEventType) {
            $this->updatedEventType = null;

            return $eventType;
        }

        $k = $this::modelName().'Updated';
        foreach (EventType::cases() as $eventType) {
            if ($eventType->name == $k) {
                return $eventType;
            }
        }

        return null;
    }

    /**
     * Gets the event name for a delete event.
     */
    public function getDeletedEventType(): ?EventType
    {
        $k = $this::modelName().'Deleted';
        foreach (EventType::cases() as $eventType) {
            if ($eventType->name == $k) {
                return $eventType;
            }
        }

        return null;
    }

    //
    // Setters
    //

    /**
     * Sets the event type for the next update event.
     */
    public function setUpdatedEventType(EventType $eventType): void
    {
        $this->updatedEventType = $eventType;
    }
}
