<?php

namespace App\AccountsReceivable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int    $id
 * @property string $name
 * @property bool   $enabled
 * @property int    $order
 */
class DisputeReason extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'order' => new Property(
                type: Type::INTEGER,
                default: 1,
            ),
        ];
    }
}
