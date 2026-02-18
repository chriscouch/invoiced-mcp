<?php

namespace App\Core\Entitlements\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use DateTimeInterface;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int               $product_id
 * @property Product           $product
 * @property DateTimeInterface $installed_on
 */
class InstalledProduct extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'product' => new Property(
                required: true,
                belongs_to: Product::class,
            ),
            'installed_on' => new Property(
                type: Type::DATETIME,
                required: true,
            ),
        ];
    }
}
