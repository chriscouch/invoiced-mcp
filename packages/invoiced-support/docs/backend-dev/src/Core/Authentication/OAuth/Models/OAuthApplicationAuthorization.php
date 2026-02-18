<?php

namespace App\Core\Authentication\OAuth\Models;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property User             $user
 * @property Company|null     $tenant
 * @property OAuthApplication $application
 * @property array            $scopes
 */
class OAuthApplicationAuthorization extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'user' => new Property(
                belongs_to: OAuthApplication::class,
            ),
            'tenant' => new Property(
                null: true,
                belongs_to: Company::class,
            ),
            'application' => new Property(
                belongs_to: OAuthApplication::class,
            ),
            'scopes' => new Property(
                type: Type::ARRAY,
            ),
        ];
    }
}
