<?php

namespace App\Core\Entitlements\Models;

use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * @property int       $id
 * @property QuotaType $quota_type
 * @property int       $limit
 */
class Quota extends MultitenantModel
{
    protected static function getProperties(): array
    {
        return [
            'quota_type' => new Property(
                type: Type::ENUM,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                enum_class: QuotaType::class,
            ),
            'limit' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
        ];
    }

    public function getTablename(): string
    {
        return 'Quotas'; // Inflector does not give the right tablename
    }
}
