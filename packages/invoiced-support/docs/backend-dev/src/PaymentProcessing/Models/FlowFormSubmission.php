<?php

namespace App\PaymentProcessing\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property int    $id
 * @property string $reference
 * @property string $data
 */
class FlowFormSubmission extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'reference' => new Property(),
            'data' => new Property(),
        ];
    }

    public static function saveResult(string $reference, string $data): void
    {
        $model = new self();
        $model->reference = $reference;
        $model->data = $data;
        $model->saveOrFail();
    }
}
