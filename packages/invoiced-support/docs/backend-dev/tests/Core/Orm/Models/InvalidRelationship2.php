<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

class InvalidRelationship2 extends Model
{
    protected static function getProperties(): array
    {
        return [
            'invalid_relationship' => new Property(
                relation: TestModel2::class,
                relation_type: 'not a valid type',
            ),
        ];
    }
}
