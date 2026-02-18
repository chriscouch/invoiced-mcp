<?php

namespace App\Companies\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int    $tenant_id
 * @property string $note
 * @property string $created_by
 */
class CompanyNote extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'tenant_id' => new Property(
                type: Type::INTEGER,
                required: true,
            ),
            'note' => new Property(
                required: true,
            ),
            'created_by' => new Property(
                required: true,
            ),
        ];
    }
}
