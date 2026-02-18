<?php

namespace App\Core\Entitlements\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;

/**
 * @property Product $product
 * @property string  $feature
 */
class ProductFeature extends Model
{
    protected static function getProperties(): array
    {
        return [
            'product' => new Property(
                required: true,
                belongs_to: Product::class,
            ),
            'feature' => new Property(
                required: true,
            ),
        ];
    }
}
