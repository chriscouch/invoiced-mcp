<?php

namespace App\Imports\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;

/**
 * Representation of objects imported from other accounting systems.
 *
 * @property int    $id
 * @property int    $import
 * @property int    $object
 * @property string $object_id
 */
class ImportedObject extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'import' => new Property(
                type: Type::INTEGER,
                required: true,
                relation: Import::class,
            ),
            'object' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'object_id' => new Property(
                type: Type::STRING,
                required: true,
            ),
        ];
    }

    /**
     * Creates a new instance of ImportedObject using the provided values.
     */
    public static function from(?int $importId, int $object, string $objectId): ImportedObject
    {
        return new ImportedObject([
            'import' => $importId,
            'object' => $object,
            'object_id' => $objectId,
        ]);
    }

    /**
     * Creates a new instance of ImportedObject with a model instance.
     */
    public static function fromModel(Model $model, ?int $importId): ImportedObject
    {
        return new ImportedObject([
            'import' => $importId,
            'object' => ObjectType::fromModel($model)->value,
            'object_id' => $model->id(),
        ]);
    }
}
