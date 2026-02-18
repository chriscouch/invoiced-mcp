<?php

namespace App\ActivityLog\ValueObjects;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Models\Event;

/**
 * Represents an event that is pending write to the DB.
 */
class PendingEvent
{
    /**
     * This allows certain event types to be given
     * a higher priority when merging happens.
     * The default score for all event types is 1.
     * When merging an event type with a higher
     * score will be used over a lower score.
     */
    private static array $typeOrdering = [
        // invoices
        EventType::InvoiceCreated->value => 3,
        EventType::InvoicePaid->value => 2,
    ];

    /**
     * @param array $associations optional event associations
     * @param array $previous     optional previous attributes
     * @param array $parameters   optional parameters to override
     */
    public function __construct(
        private EventObjectInterface $object,
        private EventType $type,
        private array $extraObjectData = [],
        private array $associations = [],
        private array $previous = [],
        private array $parameters = []
    ) {
    }

    //
    // Getters
    //

    /**
     * Gets the subject of the event.
     */
    public function getObject(): EventObjectInterface
    {
        return $this->object;
    }

    /**
     * Gets the type of the event.
     */
    public function getType(): EventType
    {
        return $this->type;
    }

    /**
     * Gets the extra information that will be added to the object stored with the event.
     */
    public function getExtraObjectData(): array
    {
        return $this->extraObjectData;
    }

    /**
     * Gets the object IDs associated with the event.
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }

    /**
     * Gets the previous object values (for updates).
     */
    public function getPrevious(): array
    {
        return $this->previous;
    }

    /**
     * Gets the extra parameters used to create the event.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Checks if the event is a create.
     */
    public function isCreate(): bool
    {
        return false;
    }

    /**
     * Checks if the event is an update.
     */
    public function isUpdate(): bool
    {
        return false;
    }

    /**
     * Checks if the event is a delete.
     */
    public function isDelete(): bool
    {
        return false;
    }

    /**
     * Checks whether this event can be merged into the given
     * previous event. This function does not check that both
     * events are for the same object, which should already be true.
     */
    public function canMergeInto(self $previousEvent): bool
    {
        if (EventType::InvoicePaid == $this->getType() && $previousEvent->isCreate()) {
            return false;
        }

        return $previousEvent->isCreate() || $previousEvent->isUpdate();
    }

    //
    // Setters
    //

    /**
     * Merges (sets) the object on the event.
     */
    public function mergeObject(EventObjectInterface $object): void
    {
        $this->object = $object;
    }

    /**
     * Merges the type into the event.
     */
    public function mergeType(EventType $type): void
    {
        // score each event type (minimum is 1)
        $currentScore = max(1, (int) array_value(self::$typeOrdering, $this->type->value));
        $newScore = max(1, (int) array_value(self::$typeOrdering, $type->value));

        // If the newer type has a higher score
        // then use it. Otherwise retain the current event type
        // for equal scores.
        if ($newScore > $currentScore) {
            $this->type = $type;
        }
    }

    /**
     * Merges the given values into the existing previous values.
     */
    public function mergePrevious(array $newPrevious): void
    {
        $this->previous = array_replace($newPrevious, $this->previous);
    }
}
