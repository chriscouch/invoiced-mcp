<?php

namespace App\Companies\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property string $type
 * @property string $template
 * @property int    $next
 */
class AutoNumberSequence extends MultitenantModel
{
    protected static function getIDProperties(): array
    {
        return ['tenant_id', 'type'];
    }

    protected static function getProperties(): array
    {
        return [
            'type' => new Property(
                required: true,
            ),
            'template' => new Property(
                required: true,
            ),
            'next' => new Property(
                type: Type::INTEGER,
                default: 1,
            ),
        ];
    }
}
