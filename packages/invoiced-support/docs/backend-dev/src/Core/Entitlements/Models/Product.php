<?php

namespace App\Core\Entitlements\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

/**
 * @property int              $id
 * @property string           $name
 * @property ProductFeature[] $features
 */
class Product extends Model
{
    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'features' => new Property(
                has_many: ProductFeature::class,
            ),
        ];
    }
}
