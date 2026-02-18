<?php

namespace App\Metadata\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Metadata\Libs\CustomFieldRepository;
use App\Metadata\Libs\MetadataListener;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int    $internal_id
 * @property string $id
 * @property string $name
 * @property string $object
 * @property string $type
 * @property array  $choices
 * @property bool   $external
 */
class CustomField extends MultitenantModel
{
    use AutoTimestamps;

    const FIELD_TYPE_STRING = 'string';
    const FIELD_TYPE_BOOLEAN = 'boolean';
    const FIELD_TYPE_DOUBLE = 'double';
    const FIELD_TYPE_INTEGER = 'integer';
    const FIELD_TYPE_ENUM = 'enum';
    const FIELD_TYPE_DATE = 'date';
    const FIELD_TYPE_MONEY = 'money';

    protected static function getProperties(): array
    {
        return [
            'internal_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::IMMUTABLE,
            ),
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: [
                    ['callable', 'fn' => [self::class, 'validateID']],
                ],
            ),
            'name' => new Property(
                required: true,
            ),
            'type' => new Property(
                validate: ['enum', 'choices' => [
                    'string',
                    'boolean',
                    'double',
                    'integer',
                    'enum',
                    'date',
                    'money',
                ]],
                default: self::FIELD_TYPE_STRING,
            ),
            'object' => new Property(
                required: true,
                validate: ['enum', 'choices' => [
                    'credit_note',
                    'customer',
                    'estimate',
                    'invoice',
                    'item',
                    'line_item',
                    'payment',
                    'plan',
                    'subscription',
                    'transaction',
                ]],
            ),
            'choices' => new Property(
                type: Type::ARRAY,
                default: [],
            ),
            'external' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
        ];
    }

    protected static function getIDProperties(): array
    {
        return ['internal_id'];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'uniqueId']);
        // bust the custom field cache
        self::afterPersist(function (AbstractEvent $event): void {
            /** @var self $model */
            $model = $event->getModel();
            CustomFieldRepository::get($model->tenant())->clearCache();
        });
    }

    public static function uniqueId(ModelCreating $event): void
    {
        /** @var self $customField */
        $customField = $event->getModel();
        $query = CustomField::where('id', $customField->id)
            ->where('object', $customField->object);

        if ($query->count() > 0) {
            throw new ListenerException('Given ID is already taken by another custom field: '.$customField->id, ['field' => 'id']);
        }
    }

    //
    // Setters
    //

    /**
     * Sets the choices value.
     */
    public static function setChoicesValue(mixed $choices): array
    {
        if (!is_array($choices)) {
            return [];
        }

        return array_unique($choices);
    }

    //
    // Validators
    //

    /**
     * Validates a user-supplied ID.
     */
    public static function validateID(mixed $id): bool
    {
        if (!is_string($id)) {
            return false;
        }

        // Allowed characters: a-z, A-Z, 0-9, _, -
        // Min length: 2
        // Max length: 40
        return preg_match('/^[a-z0-9_-]{2,'.MetadataListener::MAX_KEY_SIZE.'}$/i', $id) > 0;
    }
}
