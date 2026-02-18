<?php

namespace App\Core\Files\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\RestApi\Traits\ApiObjectTrait;

/**
 * Associates a file with a model.
 *
 * @property File $file
 * @property int  $file_id
 */
abstract class AbstractAttachment extends MultitenantModel
{
    use ApiObjectTrait;
    use AutoTimestamps;

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['object'] = $this->object;
        $result['file'] = $this->file->toArray();

        return $result;
    }
}
