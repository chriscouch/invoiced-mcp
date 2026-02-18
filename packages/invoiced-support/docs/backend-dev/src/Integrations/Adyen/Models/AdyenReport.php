<?php

namespace App\Integrations\Adyen\Models;

use App\Core\Orm\Model;
use App\Integrations\Adyen\Enums\ReportType;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $filename
 * @property ReportType  $report_type
 * @property bool        $processed
 * @property string|null $error
 */
class AdyenReport extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'filename' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'report_type' => new Property(
                type: Type::ENUM,
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                enum_class: ReportType::class,
            ),
            'processed' => new Property(
                type: Type::BOOLEAN,
            ),
            'error' => new Property(
                null: true,
            ),
        ];
    }
}
