<?php

namespace App\Integrations\AccountingSync\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\ValueObjects\AccountingObjectReference;
use App\Integrations\AccountingSync\ValueObjects\InvoicedObjectReference;
use App\Integrations\Traits\HasIntegrationTrait;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Property;
use App\Core\Orm\Query;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int             $id
 * @property int             $timestamp
 * @property string          $object
 * @property int|string|null $object_id
 * @property string|null     $accounting_id
 * @property string|null     $description
 * @property string          $level
 * @property string          $message
 * @property bool            $retry
 * @property object          $retry_context
 * @property int|null        $retried_at
 */
class ReconciliationError extends MultitenantModel
{
    use AutoTimestamps;
    use HasIntegrationTrait;

    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';

    protected static function getProperties(): array
    {
        return [
            'timestamp' => new Property(
                type: Type::DATE_UNIX,
                required: true,
            ),
            'object' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'object_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
            ),
            'accounting_id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                null: true,
            ),
            'description' => new Property(
                null: true,
            ),
            'level' => new Property(
                required: true,
                validate: ['enum', 'choices' => ['warning', 'error']],
                default: self::LEVEL_ERROR,
            ),
            'message' => new Property(),
            'retry' => new Property(
                type: Type::BOOLEAN,
            ),
            'retry_context' => new Property(
                type: Type::OBJECT,
                in_array: false,
            ),
            'retried_at' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
        ];
    }

    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->sort('id DESC');
    }

    protected function initialize(): void
    {
        parent::initialize();
        self::creating([self::class, 'onCreating']);
    }

    public static function onCreating(ModelCreating $event): void
    {
        /** @var self $error */
        $error = $event->getModel();
        if (!$error->timestamp) {
            $error->timestamp = time();
        }
    }

    public static function makeHighLevelError(int $integrationId, ObjectType $objectType, string $message): self
    {
        $error = new self();
        $error->integration_id = $integrationId;
        $error->object = $objectType->typeName();
        $error->message = $message;
        $error->saveOrFail();

        return $error;
    }

    public static function makeReadError(string $readerId, AccountingObjectReference $object, string $message): self
    {
        // use the existing reconciliation error for this object if it exists
        $error = self::where('object', $object->objectType)
            ->where('integration_id', $object->integrationType->value)
            ->where('accounting_id', $object->accountingId)
            ->oneOrNull();

        if (!$error) {
            $error = new self();
            $error->object = $object->objectType;
            $error->setIntegration($object->integrationType);
            $error->accounting_id = $object->accountingId;
        }

        if ($invoicedId = $object->invoicedId) {
            $error->object_id = $invoicedId;
        }
        if ($description = $object->description) {
            $error->description = $description;
        }
        $error->message = $message;
        $error->retry = false;
        $error->retried_at = null;
        $error->retry_context = (object) [
            'reader' => $readerId,
            'object' => $object->objectType,
            // Some implementations use accountingId and accounting_id interchangeably
            'accountingId' => $object->accountingId,
            'accounting_id' => $object->accountingId,
            // Some implementations use invoicedId and object_id interchangeably
            'invoicedId' => $object->invoicedId,
            'object_id' => $object->invoicedId,
        ];
        $error->saveOrFail();

        return $error;
    }

    public static function makeWriteError(int $integrationId, InvoicedObjectReference $object, string $message, string $eventName, string $level = self::LEVEL_ERROR): self
    {
        // use the existing reconciliation error for this transaction if it exists
        $error = self::where('object', $object->getObjectType())
            ->where('object_id', $object->getInvoicedId())
            ->oneOrNull();

        // if none exists then we'll create a new one
        if (!$error) {
            $error = new self();
        }

        $error->object = $object->getObjectType();
        $error->object_id = $object->getInvoicedId();
        if ($description = $object->getDescription()) {
            $error->description = $description;
        }
        $error->level = $level;
        $error->integration_id = $integrationId;
        $error->message = $message;
        $error->retry = false;
        $error->retry_context = (object) ['e' => $eventName];
        $error->retried_at = null;
        $error->saveOrFail();

        return $error;
    }
}
