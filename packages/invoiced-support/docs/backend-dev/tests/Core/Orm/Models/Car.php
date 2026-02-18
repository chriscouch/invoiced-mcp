<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

class Car extends Model
{
    protected static function getProperties(): array
    {
        return [
            'make' => new Property(),
            'model' => new Property(),
            'garage' => new Property(
                belongs_to: Garage::class,
            ),
        ];
    }
}
