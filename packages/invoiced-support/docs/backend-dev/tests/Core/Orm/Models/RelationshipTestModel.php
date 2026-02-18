<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

class RelationshipTestModel extends Model
{
    protected static function getProperties(): array
    {
        return [
            'person' => new Property(
                persisted: false,
                in_array: true,
            ),
        ];
    }

    protected function getPersonValue(): Person
    {
        return new Person(['id' => 10, 'name' => 'Bob Loblaw', 'email' => 'bob@example.com']);
    }
}
