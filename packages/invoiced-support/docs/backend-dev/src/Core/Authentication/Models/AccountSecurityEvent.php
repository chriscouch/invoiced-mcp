<?php

namespace App\Core\Authentication\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property int         $id
 * @property int         $user_id
 * @property string      $type
 * @property string      $ip
 * @property string      $user_agent
 * @property string|null $auth_strategy
 * @property string      $description
 */
class AccountSecurityEvent extends Model
{
    use AutoTimestamps;

    const LOGIN = 'user.login';
    const LOGOUT = 'user.logout';
    const CHANGE_PASSWORD = 'user.change_password';
    const RESET_PASSWORD_REQUEST = 'user.request_password_reset';
    const VERIFY_MFA = 'user.verify_mfa';

    protected static function getProperties(): array
    {
        return [
            'user_id' => new Property(
                required: true,
                in_array: false,
            ),
            'type' => new Property(
                required: true,
            ),
            'ip' => new Property(
                required: true,
            ),
            'user_agent' => new Property(
                required: true,
            ),
            'auth_strategy' => new Property(
                null: true,
            ),
            'description' => new Property(),
        ];
    }
}
