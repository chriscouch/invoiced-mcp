<?php

namespace App\ActivityLog\Libs;

use App\ActivityLog\Interfaces\EventObjectInterface;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\PendingCreateEvent;
use App\ActivityLog\ValueObjects\PendingDeleteEvent;
use App\ActivityLog\ValueObjects\PendingUpdateEvent;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelDeleted;
use App\Core\Orm\Event\ModelDeleting;
use App\Core\Orm\Event\ModelUpdated;
use App\Core\Orm\Event\ModelUpdating;
use App\Core\Orm\Model;

class EventModelListener
{
    const PRIORITY = -255;

    private static self $listener;

    private array $previousValues = [];
    private array $metadata = [];
    private array $associations = [];
    private DiffCalculator $differ;

    /**
     * Records the model created event.
     */
    public function onCreated(ModelCreated $event): void
    {
        // do not bother preparing an event if recording is disabled globally
        if (EventSpool::recordingDisabled()) {
            return;
        }

        /** @var EventObjectInterface|Model $model */
        $model = $event->getModel();
        $eventName = $model->getCreatedEventType();
        if (!$eventName) {
            return;
        }

        $pendingEvent = new PendingCreateEvent($model, $eventName);

        EventSpoolFacade::get()->enqueue($pendingEvent);
    }

    /**
     * Handles the model updating event.
     */
    public function onUpdating(ModelUpdating $event): void
    {
        // do not bother preparing an event if recording is disabled globally
        if (EventSpool::recordingDisabled()) {
            return;
        }

        $model = $event->getModel();
        $k = $model::modelName().'.'.$model->id();

        // set the previous values using the current model state
        // (unless this has been set by a previous update within the same request)
        if (!isset($this->previousValues[$k])) {
            $this->previousValues[$k] = $model->ignoreUnsaved()->toArray();
            if ($model instanceof MetadataModelInterface) {
                $this->previousValues[$k]['metadata'] = $model->getPersistedMetadata();
            }
        }
    }

    /**
     * Records the model updated event.
     */
    public function onUpdated(ModelUpdated $event): void
    {
        // do not bother preparing an event if recording is disabled globally
        if (EventSpool::recordingDisabled()) {
            return;
        }

        /** @var EventObjectInterface|Model $model */
        $model = $event->getModel();

        // compute the diff from previous values
        $k = $model::modelName().'.'.$model->id();
        $previous = $this->previousValues[$k];
        $updated = $model->toArray();

        if (!isset($this->differ)) {
            $this->differ = new DiffCalculator();
        }
        $diff = (array) $this->differ->diff($previous, $updated);

        // do not record update events when nothing changed
        if (!$diff) {
            return;
        }

        // now we can record the updated event
        $eventName = $model->getUpdatedEventType();
        if (!$eventName) {
            return;
        }

        $pendingEvent = new PendingUpdateEvent($model, $eventName, [], [], $diff);

        EventSpoolFacade::get()->enqueue($pendingEvent);
    }

    /**
     * Handles the model deleting event.
     */
    public function onDeleting(ModelDeleting $event): void
    {
        // do not bother preparing an event if recording is disabled globally
        if (EventSpool::recordingDisabled()) {
            return;
        }

        /** @var EventObjectInterface|Model $model */
        $model = $event->getModel();
        $k = $model::modelName().'.'.$model->id();

        // Get a snapshot of the object here
        // because by the time that the event is
        // written the object will be long gone.
        $this->metadata[$k] = $model->getEventObject();

        $this->associations[$k] = $model->getEventAssociations();
    }

    /**
     * Records the model deleted event.
     */
    public function onDeleted(ModelDeleted $event): void
    {
        // do not bother preparing an event if recording is disabled globally
        if (EventSpool::recordingDisabled()) {
            return;
        }

        /** @var EventObjectInterface|Model $model */
        $model = $event->getModel();
        $eventName = $model->getDeletedEventType();
        if (!$eventName) {
            return;
        }

        $k = $model::modelName().'.'.$model->id();
        $pendingEvent = new PendingDeleteEvent($model, $eventName, $this->metadata[$k], $this->associations[$k]);

        EventSpoolFacade::get()->enqueue($pendingEvent);
    }

    /**
     * Installs the event listeners for a given model.
     */
    public static function add(Model $model): void
    {
        if (!isset(self::$listener)) {
            self::$listener = new self();
        }

        $model::created([self::$listener, 'onCreated'], self::PRIORITY);
        $model::updating([self::$listener, 'onUpdating']);
        $model::updated([self::$listener, 'onUpdated'], self::PRIORITY);
        $model::deleting([self::$listener, 'onDeleting']);
        $model::deleted([self::$listener, 'onDeleted'], self::PRIORITY);
    }
}
