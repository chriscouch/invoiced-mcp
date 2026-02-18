<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\SoftDelete;
use App\Core\Orm\Type;

class Person extends Model
{
    use SoftDelete;

    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                type: Type::STRING,
            ),
            'name' => new Property(
                type: Type::STRING,
                default: 'Jared',
            ),
            'email' => new Property(
                type: Type::STRING,
                validate: 'email',
            ),
            'garage' => new Property(
                has_one: Garage::class,
            ),
        ];
    }
}
