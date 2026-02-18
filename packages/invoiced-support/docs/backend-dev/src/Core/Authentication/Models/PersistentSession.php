<?php

namespace App\Core\Authentication\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int    $user_id
 * @property string $email
 * @property string $series
 * @property string $token
 * @property bool   $two_factor_verified
 */
class PersistentSession extends Model
{
    use AutoTimestamps;

    public static int $sessionLength = 7776000; // 3 months in seconds

    protected static function getIDProperties(): array
    {
        return ['token'];
    }

    protected static function getProperties(): array
    {
        return [
            'user_id' => new Property(
                type: Type::INTEGER,
            ),
            'email' => new Property(
                validate: 'email',
            ),
            'series' => new Property(
                required: true,
            ),
            'token' => new Property(
                required: true,
            ),
            'two_factor_verified' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
        ];
    }
}
