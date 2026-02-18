<?php

namespace App\Tests\Core\RestApi;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

class Book extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(),
            'author' => new Property(),
        ];
    }

    protected function getMassAssignmentAllowed(): ?array
    {
        return ['name', 'author'];
    }
}
