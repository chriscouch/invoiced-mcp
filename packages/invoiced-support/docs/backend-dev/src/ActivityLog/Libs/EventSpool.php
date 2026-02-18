<?php

namespace App\ActivityLog\Libs;

use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\PendingEvent;
use App\Core\Orm\Model;

/**
 * The event spool is the entry point for events
 * that need to be written. It spools events pending
 * write until either the spool is full or the request
 * has finished. Events are typically written after
 * a request has completed so that a user does not
 * need to wait for events to be written.
 */
class EventSpool
{
    const MAX_QUEUE_SIZE = 100;
    private static int $disabled = 0;

    /** @var PendingEvent[] */
    private array $events = [];

    public function __construct(private EventWriter $writer)
    {
    }

    public function __destruct()
    {
        $this->flush(true);
    }

    /**
     * Queues up an event for a model to be created later.
     */
    public function enqueue(PendingEvent $event): void
    {
        if (self::recordingDisabled()) {
            return;
        }

        // determine the type of event, update, delete, or other,
        // in order to merge multiple events for the same object
        if ($event->isUpdate()) {
            $this->enqueueUpdatedEvent($event);
        } elseif ($event->isDelete()) {
            $this->enqueueDeletedEvent($event);
        } else {
            $this->enqueueEvent($event);
        }

        // flush the queue once we reach the max size
        if (count($this->events) >= self::MAX_QUEUE_SIZE) {
            $this->flush();
        }
    }

    /**
     * Gets the size of the queue.
     */
    public function size(): int
    {
        return count($this->events);
    }

    /**
     * Clears out the queue.
     */
    public function clear(): void
    {
        $this->events = [];
    }

    /**
     * Writes out any events to the database.
     */
    public function flush(bool $force = false): void
    {
        // we should not apply save events in case ongoing DB transaction
        // to make sure we always flush the event queue - we need to use
        // $force at some point. Currently it is used in __destruct method
        // So, if queue is destroyed manually before the end of the application
        // - it may cause this bug
        if ($force || !$this->writer->transactionInProgress()) {
            while (count($this->events) > 0) {
                $events = array_splice($this->events, 0, self::MAX_QUEUE_SIZE);
                $this->writer->write($events);
            }
        }
    }

    /**
     * Returns the element at the beginning of the spool.
     */
    public function peek(): ?PendingEvent
    {
        if (0 === $this->size()) {
            return null;
        }

        return $this->events[0];
    }

    //
    // Enable/Disable
    //

    /**
     * Enables event recording.
     */
    public static function enable(): void
    {
        self::$disabled = 0;
    }

    /**
     * Disabled event recording.
     */
    public static function disable(): void
    {
        self::$disabled = 1;
    }

    /**
     * Increments event recording enabled status.
     */
    public static function enablePop(): void
    {
        --self::$disabled;
    }

    /**
     * Decrements event recording enabled status.
     */
    public static function disablePush(): void
    {
        ++self::$disabled;
    }

    /**
     * Checks if event recording is disabled.
     */
    public static function recordingDisabled(): bool
    {
        return self::$disabled > 0;
    }

    //
    // Helpers / Private Methods
    //

    /**
     * Enqueues an ordinary event.
     */
    private function enqueueEvent(PendingEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * Enqueues an updated event.
     */
    private function enqueueUpdatedEvent(PendingEvent $event): void
    {
        // Look for a past *.created or *.updated
        // event for this object.
        $object = $event->getObject();
        $events = $this->getEventsForObject($object);

        $previousEvent = false;
        foreach ($events as &$_previousEvent) {
            if ($event->canMergeInto($_previousEvent)) {
                $previousEvent = &$_previousEvent;

                break;
            }
        }

        // Merge this event into the previous event.
        if ($previousEvent) {
            // Merge the object (model) into the previous event.
            // Since the model is not serialized until the queue
            // is flushed, the model reference needs to be updated
            // to that of the most recent event to ensure the most
            // update to date values are serialized.
            $previousEvent->mergeObject($event->getObject());

            // Merge the name into the previous event. There is
            // a specific order of operations for names that
            // we need to follow. For example, we would prefer
            // to use invoice.paid over the generic invoice.updated.
            $previousEvent->mergeType($event->getType());

            // When merging with other *.updated events then
            // the previous attributes must be merged.
            if ($previousEvent->isUpdate()) {
                // if there are any conflicting values then
                // overwrite transient previous values with
                // older previous values
                // i.e. invoice.total = 100 -> 150 -> 200
                // then previous.total should be 100, not 150
                $previousEvent->mergePrevious($event->getPrevious());
            }
        } else {
            $this->events[] = $event;
        }
    }

    /**
     * Enqueues a deleted event.
     */
    private function enqueueDeletedEvent(PendingEvent $event): void
    {
        // Look for a past *.created event
        $hasCreated = false;
        $object = $event->getObject();
        $events = $this->getEventsForObject($object);
        foreach ($events as $_previousEvent) {
            if ($_previousEvent->isCreate()) {
                $hasCreated = true;

                break;
            }
        }

        // Do not record this event and remove any previous events
        // about the object if there is also a *.created event.
        if ($hasCreated) {
            $this->removeEventsForObject($object);
        } else {
            $this->events[] = $event;
        }
    }

    /**
     * Finds all events in the event queue for an object.
     *
     * @return PendingEvent[]
     */
    private function getEventsForObject(Model $object): array
    {
        $events = [];
        foreach ($this->events as &$event) {
            $object2 = $event->getObject();

            // first verify objects are of the same type
            if (!($object2 instanceof $object)) {
                continue;
            }

            // then compare each ID
            $ids1 = $object->ids();
            $ids2 = $object2->ids();
            $match = true;
            foreach ($ids1 as $k => $v) {
                if ($ids2[$k] !== $ids1[$k]) {
                    $match = false;

                    break;
                }
            }

            if ($match) {
                $events[] = &$event;
            }
        }

        return $events;
    }

    /**
     * Removes any events for an object from the queue.
     */
    private function removeEventsForObject(Model $object): void
    {
        $delete = [];

        // find all matching events
        foreach ($this->events as $k => $event) {
            $object2 = $event->getObject();
            if ($object2 instanceof $object && $object2->id() === $object->id()) {
                $delete[] = $k;
            }
        }

        // and delete them
        rsort($delete);
        foreach ($delete as $k) {
            unset($this->events[$k]);
        }
    }
}
