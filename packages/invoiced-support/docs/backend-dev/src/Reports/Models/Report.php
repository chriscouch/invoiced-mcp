<?php

namespace App\Reports\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int         $id
 * @property string      $type
 * @property string      $title
 * @property string      $filename
 * @property int         $timestamp
 * @property array       $data
 * @property string      $csv_url
 * @property string      $pdf_url
 * @property string|null $definition
 * @property array|null  $parameters
 */
class Report extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'type' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
            ),
            'title' => new Property(),
            'filename' => new Property(),
            'timestamp' => new Property(
                type: Type::DATE_UNIX,
                required: true,
            ),
            'data' => new Property(
                type: Type::ARRAY,
            ),
            'definition' => new Property(
                null: true,
            ),
            'parameters' => new Property(
                type: Type::ARRAY,
                null: true,
            ),
            'csv_url' => new Property(),
            'pdf_url' => new Property(),
        ];
    }
}
