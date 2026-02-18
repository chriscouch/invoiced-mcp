<?php

namespace App\Tests\Core\Orm\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

class Post extends Model
{
    protected static function getProperties(): array
    {
        return [
            'category' => new Property(
                belongs_to: Category::class,
            ),
        ];
    }
}
