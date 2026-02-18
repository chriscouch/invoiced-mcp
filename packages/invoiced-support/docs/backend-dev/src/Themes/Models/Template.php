<?php

namespace App\Themes\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property int    $id
 * @property string $filename
 * @property string $content
 * @property bool   $enabled
 */
class Template extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'filename' => new Property(
                required: true,
                validate: ['unique', 'column' => 'filename'],
            ),
            'content' => new Property(
                required: true,
            ),
            'enabled' => new Property(
                type: Type::BOOLEAN,
                default: true,
            ),
        ];
    }

    /**
     * Gets the contents of a template if
     * the template exists and is enabled.
     */
    public static function getContent(string $filename): ?string
    {
        $template = self::where('filename', $filename)
            ->where('enabled', true)
            ->oneOrNull();

        return $template ? $template->content : null;
    }
}
