<?php

namespace App\Core\Authentication\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property User   $user
 * @property string $identifier
 */
class LoginDevice extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'user' => new Property(
                belongs_to: User::class,
            ),
            'identifier' => new Property(),
        ];
    }
}
