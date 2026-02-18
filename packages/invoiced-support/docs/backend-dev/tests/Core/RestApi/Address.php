<?php

namespace App\Tests\Core\RestApi;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property string $street
 * @property string $city
 * @property string $state
 */
class Address extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'street' => new Property(),
            'city' => new Property(),
            'state' => new Property(),
        ];
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        unset($result['updated_at']);

        return $result;
    }
}
