<?php

namespace App\Core\Authentication\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string $id
 * @property int    $user_id
 * @property string $ip
 * @property string $user_agent
 * @property int    $expires
 * @property bool   $valid
 */
class ActiveSession extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'user_id' => new Property(
                required: true,
                in_array: false,
            ),
            'ip' => new Property(
                required: true,
            ),
            'user_agent' => new Property(
                required: true,
            ),
            'expires' => new Property(
                type: Type::DATE_UNIX,
            ),
            'valid' => new Property(
                type: Type::BOOLEAN,
                default: true,
                in_array: false,
            ),
        ];
    }
}
