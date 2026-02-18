<?php

namespace App\Companies\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int    $id
 * @property int    $tenant_id
 * @property string $utm_campaign
 * @property string $utm_source
 * @property string $utm_content
 * @property string $utm_medium
 * @property string $utm_term
 * @property string $initial_referrer
 * @property string $initial_referring_domain
 */
class MarketingAttribution extends Model
{
    protected static function getProperties(): array
    {
        return [
            'id' => new Property(
                type: Type::INTEGER,
                mutable: Property::IMMUTABLE,
                in_array: false,
            ),
            'tenant_id' => new Property(
                type: Type::INTEGER,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                in_array: false,
            ),
            'utm_campaign' => new Property(),
            'utm_source' => new Property(),
            'utm_content' => new Property(),
            'utm_medium' => new Property(),
            'utm_term' => new Property(),
            '$initial_referrer' => new Property(),
            '$initial_referring_domain' => new Property(),
        ];
    }
}
