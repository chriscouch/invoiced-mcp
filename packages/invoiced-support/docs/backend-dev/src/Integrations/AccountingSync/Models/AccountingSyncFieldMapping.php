<?php

namespace App\Integrations\AccountingSync\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\AccountingSync\Enums\SyncDirection;
use App\Integrations\AccountingSync\Enums\TransformFieldType;
use App\Integrations\Enums\IntegrationType;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int                $id
 * @property IntegrationType    $integration
 * @property string             $object_type
 * @property string             $source_field
 * @property string             $destination_field
 * @property TransformFieldType $data_type
 * @property SyncDirection      $direction
 * @property bool               $enabled
 * @property string|null        $value
 */
class AccountingSyncFieldMapping extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'integration' => new Property(
                type: Type::ENUM,
                enum_class: IntegrationType::class,
            ),
            'direction' => new Property(
                type: Type::ENUM,
                enum_class: SyncDirection::class,
            ),
            'object_type' => new Property(),
            'source_field' => new Property(),
            'value' => new Property(
                null: true,
            ),
            'destination_field' => new Property(),
            'data_type' => new Property(
                type: Type::ENUM,
                enum_class: TransformFieldType::class,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    /**
     * @return self[]
     */
    public static function getForDataFlow(IntegrationType $integrationType, SyncDirection $direction, string $objectType): array
    {
        return AccountingSyncFieldMapping::where('integration', $integrationType->value)
            ->where('direction', $direction->value)
            ->where('object_type', $objectType)
            ->where('enabled', true)
            ->all()
            ->toArray();
    }
}
