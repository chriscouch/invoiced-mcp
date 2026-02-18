<?php

namespace App\ActivityLog\Models;

use App\Automations\Interfaces\AutomationEventInterface;
use App\Core\Authentication\Models\User;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Type;
use App\Core\Statsd\StatsdFacade;
use App\Core\Utils\AppUrl;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Interfaces\EventStorageInterface;
use App\ActivityLog\Libs\EventMessageFactory;
use App\ActivityLog\Libs\Messages\BaseMessage;
use App\ActivityLog\ValueObjects\EventData;
use Exception;
use ICanBoogie\Inflector;
use RuntimeException;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @property int                $id
 * @property string             $type
 * @property int                $type_id
 * @property int                $timestamp
 * @property string             $object_type
 * @property int                $object_type_id
 * @property mixed              $object_id
 * @property object|null        $object
 * @property object|null        $previous
 * @property int                $user_id
 * @property array|null         $user
 * @property string|null        $href
 * @property EventAssociation[] $associations
 */
class Event extends MultitenantModel implements AutomationEventInterface
{
    private array $_associations;
    private BaseMessage $_message;
    private array $_messageArray;
    private User $_user;

    protected static function getProperties(): array
    {
        return [
            'type' => new Property(),
            'type_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'timestamp' => new Property(
                type: Type::DATE_UNIX,
            ),
            'object_type' => new Property(
                in_array: false,
            ),
            'object_type_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
            ),
            'object_id' => new Property(
                in_array: false,
            ),
            'user_id' => new Property(
                type: Type::INTEGER,
                in_array: false,
                relation: User::class,
            ),
            'object' => new Property(
                type: Type::OBJECT,
                null: true,
                in_array: false,
            ),
            'previous' => new Property(
                type: Type::OBJECT,
                null: true,
                in_array: false,
            ),
            'associations' => new Property(
                in_array: false,
                foreign_key: 'event',
                has_many: EventAssociation::class,
            ),
        ];
    }

    //
    // Model Overrides
    //

    public function create(array $data = []): bool
    {
        throw new Exception('Creating events through the ORM is not supported');
    }

    public function set(array $data = []): bool
    {
        throw new Exception('Modifying events is not allowed');
    }

    public function delete(array $data = []): bool
    {
        throw new Exception('Deleting events is not allowed');
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->sort('id DESC');
    }

    public function toArray(): array
    {
        $result = parent::toArray();

        $result['data'] = ['object' => $this->object];

        $previous = $this->previous;
        if (is_object($previous) && count(get_object_vars($previous)) > 0) {
            $result['data']['previous'] = $previous;
        }

        return $result;
    }

    //
    // Accessors
    //

    /**
     * Gets the object_type property.
     */
    protected function getObjectTypeValue(mixed $value): ?string
    {
        if ($value) {
            return $value;
        }

        // This is used for backwards compatibility with
        // newer event records
        if ($typeId = $this->object_type_id) {
            if ($objectType = ObjectType::tryFrom($typeId)) {
                return $objectType->typeName();
            }
        }

        // This is needed in case object_type is
        // empty. The ENUM type on the Events table
        // only supports a limited type of objects.
        $type = $this->type;
        if (!$type) {
            return null;
        }

        $parts = explode('.', $type);

        return $parts[0];
    }

    public function getObjectValue(mixed $input): object
    {
        if (!is_object($input)) {
            throw new RuntimeException('Event data has not been hydrated');
        }

        return $input;
    }

    /**
     * Gets the message property.
     */
    public function getMessageValue(): array
    {
        if (!isset($this->_messageArray)) {
            $stopwatch = new Stopwatch(true);
            $stopwatch->start('event.request.get_message');
            $this->_messageArray = $this->getMessage()->generate();
            StatsdFacade::get()->timing('event.request.get_message', $stopwatch->getEvent('event.request.get_message')->getDuration());
            $stopwatch->stop('event.request.get_message');
        }

        return $this->_messageArray;
    }

    /**
     * Gets the user property.
     */
    public function getUserValue(): ?array
    {
        $uid = $this->user_id;
        if (!$uid) {
            return null;
        }

        if (!isset($this->_user)) {
            // Generate a model that does not result in a database call
            // if this is a system user
            if ($this->user_id < 0) {
                $this->_user = (new User())->refreshWith(['id' => $this->user_id]);
            } else {
                $user = $this->relation('user_id');
                if ($user instanceof User) {
                    $this->_user = $user;
                } else {
                    return null;
                }
            }
        }

        return $this->_user->toArray();
    }

    /**
     * Generates the link to this event.
     */
    protected function getHrefValue(): ?string
    {
        try {
            // object_type property is a fallback for legacy records without object_type_id
            $type = ObjectType::tryFrom((int) $this->object_type_id) ?? ObjectType::fromTypeName($this->object_type);
        } catch (RuntimeException) {
            // an exception means that it does not exist as a model type
            return null;
        }

        $associations = $this->getAssociations();
        $id = $this->object_id;

        switch ($type) {
            case ObjectType::Charge:
            case ObjectType::Refund:
                $id = $associations['payment'] ?? null;

                break;
            case ObjectType::Comment:
                break;
            case ObjectType::PaymentPlan:
            case ObjectType::PromiseToPay:
                $id = $associations['invoice'];

                break;
            case ObjectType::BankAccount:
            case ObjectType::Card:
            case ObjectType::Contact:
            case ObjectType::Note:
            case ObjectType::Task:
                $id = $associations['customer'] ?? '';

                break;
        }

        switch ($this->type) {
            case EventType::EstimateCommented->value:
            case EventType::EstimateViewed->value:
                $id = 'estimates/'.($associations['estimate'] ?? '');

                break;
            case EventType::InvoiceCommented->value:
            case EventType::InvoiceViewed->value:
                $id = 'invoices/'.($associations['invoice'] ?? '');

                break;
            case EventType::CreditNoteCommented->value:
            case EventType::CreditNoteViewed->value:
                $id = 'credit_notes/'.($associations['credit_note'] ?? '');

                break;
        }

        return AppUrl::get()->getObjectLink($type, $id, ['account' => $this->tenant_id]);
    }

    //
    // Getters
    //

    /**
     * @return $this
     */
    public function hydrateFromStorage(EventStorageInterface $storage): self
    {
        $storage->hydrateEvents([$this]);

        return $this;
    }

    /**
     * @return $this
     */
    public function hydrateEventData(EventData $data): self
    {
        $this->object = $data->object;
        $this->previous = $data->previous;
        if ($data->message) {
            $this->_messageArray = $data->message;
        }

        return $this;
    }

    /**
     * Generates the message for this event.
     */
    public function getMessage(): BaseMessage
    {
        if (!isset($this->_message)) {
            $this->_message = EventMessageFactory::make($this);
        }

        return $this->_message;
    }

    /**
     * Gets the object of this event.
     */
    public function object(): ?Model
    {
        try {
            // object_type property is a fallback for legacy records without object_type_id
            $objectType = $this->getObjectType();
            $modelClass = $objectType->modelClass();

            return new $modelClass(['id' => $this->object_id]);
        } catch (RuntimeException) {
            // an exception means that it does not exist as a model type
        }

        return null;
    }

    public function getObjectType(): ObjectType
    {
        return ObjectType::tryFrom($this->object_type_id) ?? ObjectType::fromTypeName($this->object_type);
    }

    /**
     * Gets the human-readable title, i.e. Invoice viewed.
     */
    public function getTitle(): string
    {
        $inflector = Inflector::get();
        $type = str_replace('.', '_', $this->type);

        return $inflector->humanize($type);
    }

    /**
     * Gets a map of the associations with this event.
     *
     * @return array key-value map of event associations
     */
    public function getAssociations(): array
    {
        if (!isset($this->_associations)) {
            // associations can be fetch with eager loading
            $associations = $this->associations
                ? array_map(fn ($association) => [
                    $association->object,
                    $association->object_id,
                    $association->object_type,
                ], $this->associations)
                : (array) self::getDriver()->getConnection(null)->createQueryBuilder()
                    ->select('object,object_id,object_type')
                    ->from('EventAssociations')
                    ->where('event = :eventId')
                    ->setParameter('eventId', $this->id())
                    ->fetchAllNumeric();

            $this->_associations = [];
            foreach ($associations as $link) {
                [$objectType, $objectId, $objectTypeId] = $link;
                if (!$objectType && $objectTypeId) {
                    $objectType = ObjectType::from($objectTypeId)->typeName();
                }

                if ($objectType) {
                    $this->_associations[$objectType] = $objectId;
                }
            }
        }

        return $this->_associations;
    }

    //
    // Setters
    //

    /**
     * Used for testing.
     */
    public function setAssociations(array $associations): void
    {
        $this->_associations = $associations;
    }

    public function objectData(): array
    {
        return (array) ($this->object ?? $this->previous ?? []);
    }

    public function eventType(): int
    {
        return $this->type_id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function self(): self
    {
        return $this;
    }
}
