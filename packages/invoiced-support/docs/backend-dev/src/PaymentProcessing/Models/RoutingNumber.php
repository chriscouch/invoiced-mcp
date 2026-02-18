<?php

namespace App\PaymentProcessing\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property int    $id
 * @property string $routing_number
 * @property string $bank_name
 */
class RoutingNumber extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'routing_number' => new Property(
                required: true,
            ),
            'bank_name' => new Property(
                required: true,
            ),
        ];
    }
}
