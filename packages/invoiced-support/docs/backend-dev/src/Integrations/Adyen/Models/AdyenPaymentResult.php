<?php

namespace App\Integrations\Adyen\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property int    $id
 * @property string $reference
 * @property string $result
 */
class AdyenPaymentResult extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'reference' => new Property(),
            'result' => new Property(),
        ];
    }
}
