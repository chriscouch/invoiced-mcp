<?php

namespace App\Sending\Email\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int    $id
 * @property string $email_id
 * @property int    $timestamp
 * @property string $ip
 * @property string $user_agent
 */
class EmailOpen extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                type: Type::INTEGER,
                mutable: Property::IMMUTABLE,
                in_array: false,
            ),
            'email_id' => new Property(
                in_array: false,
            ),
            'timestamp' => new Property(
                type: Type::DATE_UNIX,
            ),
            'ip' => new Property(),
            'user_agent' => new Property(),
        ];
    }
}
