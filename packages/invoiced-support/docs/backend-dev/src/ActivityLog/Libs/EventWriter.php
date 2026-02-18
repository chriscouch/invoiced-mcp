<?php

namespace App\ActivityLog\Libs;

use App\Core\Authentication\Libs\UserContext;
use App\Core\Database\DatabaseHelper;
use App\Core\Orm\Hydrator;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Models\Event;
use App\ActivityLog\ValueObjects\EventData;
use App\ActivityLog\ValueObjects\PendingEvent;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * This class writes out events to the database
 * and performs any post-write actions, like
 * emitting webhooks and sending notifications.
 */
class EventWriter implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;

    const EVENT_INSERT_COLUMNS = [
        'tenant_id',
        'timestamp',
        'type',
        'type_id',
        'object_type',
        'object_type_id',
        'object_id',
        'user_id',
    ];

    const EVENT_ASSOCIATION_INSERT_COLUMNS = [
        'event',
        'object',
        'object_type',
        'object_id',
    ];

    public function __construct(
        private Connection $database,
        private EventStorageInterface $eventStorage,
        private EventDispatcherInterface $dispatcher,
        private UserContext $userContext
    ) {
    }

    /**
     * Records an event that happened to an object.
     *
     * @param PendingEvent[] $events
     */
    public function write(array $events): void
    {
        try {
            $this->_write($events);
        } catch (Throwable $e) {
            $this->logger->error('Could not write event', ['exception' => $e]);
        }

        $this->statsd->increment('event.write', count($events));
    }

    /**
     * Writes the events to the database.
     *
     * @param PendingEvent[] $events
     *
     * @throws Exception when the events cannot be written
     */
    private function _write(array $events): void
    {
        // build the data to insert
        $modelData = [];
        $associations = [];
        $eventData = [];

        foreach ($events as $pendingEvent) {
            $row = $this->buildRow($pendingEvent);
            if (!$row) {
                continue;
            }
            [$model, $associationData, $dataRow] = $row;
            $modelData = array_merge($modelData, $model);
            $associations[] = $associationData;
            $eventData[] = $dataRow;
        }

        // write the data
        $firstId = (int) DatabaseHelper::bulkInsert($this->database, 'Events', self::EVENT_INSERT_COLUMNS, $modelData);
        $this->writeAssociations($associations, $firstId);
        $this->writeEventData($eventData, $firstId);

        // dispatch the events
        $this->dispatch($modelData, $eventData, $firstId);
    }

    private function buildRow(PendingEvent $pendingEvent): ?array
    {
        $eventObject = $pendingEvent->getObject();
        $objectType = $eventObject->getEventObjectType();
        $objectId = $eventObject->getEventObjectId();
        if (!$objectId) {
            return null;
        }

        $type = $pendingEvent->getType();
        $extraObjectData = $pendingEvent->getExtraObjectData();
        $associations = $pendingEvent->getAssociations();
        $previous = $pendingEvent->getPrevious();
        $parameters = $pendingEvent->getParameters();

        // whenever an object has been deleted then the
        // argument already contains the event object
        // and associations
        if ($pendingEvent->isDelete()) {
            $object = $extraObjectData;
        } else {
            // otherwise, build the object and associations
            // from the event object
            $object = array_replace(
                $eventObject->getEventObject(),
                $extraObjectData
            );

            $associations = array_merge(
                $eventObject->getEventAssociations(),
                $associations
            );
        }

        // determine the tenant ID
        if (isset($parameters['tenant_id'])) {
            $tenantId = $parameters['tenant_id'];
        } else {
            $tenantId = $eventObject->getEventTenantId();
        }

        // determine the user ID
        if (isset($parameters['user'])) {
            $userId = $parameters['user'];
        } else {
            $user = $this->userContext->get();
            $userId = $user?->id ?: -1;
        }

        // add event object to associations
        $associations[] = [
            $objectType->typeName(), // object
            $objectId, // object_id
        ];

        // remove any duplicate association objects
        $associations = array_unique($associations, SORT_REGULAR);

        // generate the row to create the event in the database
        $insertData = [
            $tenantId, // tenant_id
            time(), // timestamp
            $type->value, // type
            $type->toInteger(), // type_id
            $objectType->typeName(), // object_type
            $objectType->value, // object_type_id
            $objectId, // object_id
            $userId, // user_id
        ];

        // pre-generate the event message
        $parameters = array_combine(self::EVENT_INSERT_COLUMNS, $insertData);
        /** @var Event[] $events */
        $events = Hydrator::hydrate([$parameters], Event::class, []);
        $event = $events[0];
        $eventObject = (object) $object;
        $event->object = $eventObject;
        $eventPrevious = count($previous) > 0 ? (object) $previous : null;
        $event->previous = $eventPrevious;
        $eventAssociations = [];
        foreach ($associations as $associationRow) {
            $eventAssociations[$associationRow[0]] = $associationRow[1];
        }
        $event->setAssociations($eventAssociations);
        $eventMessage = EventMessageFactory::make($event);

        return [
            $insertData,
            $associations,
            [
                $tenantId,
                new EventData(
                    object: $eventObject,
                    previous: $eventPrevious,
                    message: $eventMessage->generate(),
                ),
            ],
        ];
    }

    /**
     * Writes the associations that belong to already inserted events.
     *
     * @throws DBALException
     */
    private function writeAssociations(array $eventAssociations, int $eventId): void
    {
        $associationsToInsert = [];
        foreach ($eventAssociations as $eventAssociations2) {
            foreach ($eventAssociations2 as $association) {
                [$object, $objectId] = $association;
                $associationsToInsert = array_merge($associationsToInsert, [
                    $eventId, // event
                    $object, // object
                    ObjectType::fromTypeName($object)->value, // object_type
                    $objectId, // object_id
                ]);
            }
            ++$eventId;
        }

        DatabaseHelper::bulkInsert($this->database, 'EventAssociations', self::EVENT_ASSOCIATION_INSERT_COLUMNS, $associationsToInsert);
    }

    /**
     * Writes the object / previous data blobs that go with the event.
     */
    private function writeEventData(array $eventData, int $eventId): void
    {
        foreach ($eventData as $row) {
            [$tenantId, $data] = $row;
            $this->eventStorage->store($tenantId, $eventId, $data);
            ++$eventId;
        }
    }

    /**
     * Dispatches the event across the bus for listeners to add behavior.
     */
    private function dispatch(array $modelData, array $eventData, int $eventId): void
    {
        $numColumns = count(self::EVENT_INSERT_COLUMNS);
        $numRows = count($modelData) / $numColumns;
        for ($i = 0; $i < $numRows; ++$i) {
            // hydrate an event model as needed by the listeners
            $parameters = array_combine(self::EVENT_INSERT_COLUMNS, array_slice($modelData, $i * $numColumns, $numColumns));
            $parameters['id'] = $eventId;
            /** @var Event[] $events */
            $events = Hydrator::hydrate([$parameters], Event::class, []);
            $events[0]->hydrateEventData($eventData[$i][1]);

            // dispatch the event to the event bus
            $this->dispatcher->dispatch($events[0], 'object_event.dispatch');

            ++$eventId;
        }
    }

    public function transactionInProgress(): bool
    {
        return $this->database->isTransactionActive();
    }
}
