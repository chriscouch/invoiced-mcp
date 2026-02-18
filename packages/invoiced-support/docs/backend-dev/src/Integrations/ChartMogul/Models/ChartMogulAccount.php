<?php

namespace App\Integrations\ChartMogul\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string      $token
 * @property bool        $enabled
 * @property string|null $data_source
 * @property int         $sync_cursor
 * @property int|null    $last_sync_attempt
 * @property string|null $last_sync_error
 */
class ChartMogulAccount extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getIDProperties(): array
    {
        return ['tenant_id'];
    }

    protected static function getProperties(): array
    {
        return [
            'token' => new Property(
                type: Type::STRING,
                required: true,
                in_array: false,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
            'data_source' => new Property(
                type: Type::STRING,
                null: true,
            ),
            'sync_cursor' => new Property(
                type: Type::DATE_UNIX,
            ),
            'last_sync_attempt' => new Property(
                type: Type::DATE_UNIX,
                null: true,
            ),
            'last_sync_error' => new Property(
                type: Type::STRING,
                null: true,
            ),
        ];
    }
}
