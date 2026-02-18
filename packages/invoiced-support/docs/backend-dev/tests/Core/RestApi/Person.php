<?php

namespace App\Tests\Core\RestApi;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string $name
 * @property string $email
 * @property float  $balance
 * @property bool   $active
 */
class Person extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(),
            'email' => new Property(),
            'address' => new Property(
                type: Type::INTEGER,
                relation: 'Address',
            ),
            'balance' => new Property(
                type: Type::FLOAT,
                in_array: false,
            ),
            'active' => new Property(
                type: Type::BOOLEAN,
                default: false,
            ),
            'address_shim' => new Property(
                in_array: false,
                relation: 'Address',
                local_key: 'address',
            ),
        ];
    }
}
