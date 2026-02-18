<?php

namespace App\Core\Entitlements\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int    $id
 * @property string $feature
 * @property bool   $enabled
 */
class Feature extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'feature' => new Property(
                required: true,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                required: true,
            ),
        ];
    }
}
