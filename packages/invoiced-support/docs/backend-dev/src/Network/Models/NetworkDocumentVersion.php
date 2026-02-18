<?php

namespace App\Network\Models;

use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int             $id
 * @property NetworkDocument $document
 * @property int             $version
 * @property int             $size
 */
class NetworkDocumentVersion extends Model
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'document' => new Property(
                belongs_to: NetworkDocument::class,
            ),
            'version' => new Property(
                type: Type::INTEGER,
            ),
            'size' => new Property(
                type: Type::INTEGER,
            ),
        ];
    }
}
