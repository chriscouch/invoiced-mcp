<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

class Group extends Model
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(),
        ];
    }
}
