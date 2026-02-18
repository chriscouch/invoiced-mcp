<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

class Balance extends Model
{
    protected static function getProperties(): array
    {
        return [
            'person' => new Property(
                belongs_to: Person::class,
            ),
            'amount' => new Property(
                type: Type::FLOAT,
            ),
        ];
    }
}
