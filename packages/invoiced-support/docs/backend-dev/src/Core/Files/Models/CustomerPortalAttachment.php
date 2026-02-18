<?php

namespace App\Core\Files\Models;

use App\Core\Orm\Property;

/**
 * Associates a file with a model.
 *
 * @property int  $id
 * @property File $file
 */
class CustomerPortalAttachment extends AbstractAttachment
{
    protected static function getProperties(): array
    {
        return [
            'file' => new Property(
                mutable: Property::MUTABLE_CREATE_ONLY,
                required: true,
                validate: ['unique', 'column' => 'file_id'],
                in_array: false,
                belongs_to: File::class,
            ),
        ];
    }
}
