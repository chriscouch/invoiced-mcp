<?php

namespace App\ActivityLog\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int    $object_type
 * @property string $object_id
 * @property string $object
 * @property int    $event
 */
class EventAssociation extends Model
{
    protected static function getIDProperties(): array
    {
        return ['event', 'object', 'object_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'event' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'object' => new Property(
                required: true,
            ),
            'object_type' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'object_id' => new Property(
                required: true,
            ),
        ];
    }
}
