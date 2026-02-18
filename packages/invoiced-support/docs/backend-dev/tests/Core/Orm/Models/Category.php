<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

class Category extends Model
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(),
            'posts' => new Property(
                has_many: Post::class,
            ),
        ];
    }
}
