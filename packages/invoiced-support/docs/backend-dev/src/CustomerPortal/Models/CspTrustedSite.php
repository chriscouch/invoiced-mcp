<?php

namespace App\CustomerPortal\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string $url
 * @property bool   $connect
 * @property bool   $font
 * @property bool   $frame
 * @property bool   $img
 * @property bool   $media
 * @property bool   $object
 * @property bool   $script
 * @property bool   $style
 * @property int    $created_at
 */
class CspTrustedSite extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'url' => new Property(
                required: true,
                validate: [
                    ['callable', 'fn' => [self::class, 'validateUrl']],
                    'url',
                ],
            ),
            'connect' => new Property(
                type: Type::BOOLEAN,
            ),
            'font' => new Property(
                type: Type::BOOLEAN,
            ),
            'frame' => new Property(
                type: Type::BOOLEAN,
            ),
            'img' => new Property(
                type: Type::BOOLEAN,
            ),
            'media' => new Property(
                type: Type::BOOLEAN,
            ),
            'object' => new Property(
                type: Type::BOOLEAN,
            ),
            'script' => new Property(
                type: Type::BOOLEAN,
            ),
            'style' => new Property(
                type: Type::BOOLEAN,
            ),
        ];
    }

    public function getEnabledSources(): array
    {
        $result = [];
        if ($this->connect) {
            $result[] = 'connect';
        }
        if ($this->font) {
            $result[] = 'font';
        }
        if ($this->frame) {
            $result[] = 'frame';
        }
        if ($this->img) {
            $result[] = 'img';
        }
        if ($this->media) {
            $result[] = 'media';
        }
        if ($this->object) {
            $result[] = 'object';
        }
        if ($this->script) {
            $result[] = 'script';
        }
        if ($this->style) {
            $result[] = 'style';
        }

        return $result;
    }

    /**
     * Validates a URL value. Only URLs that start with http/https are valid.
     * Cannot use invoiced.com domain.
     *
     * @param string $url
     */
    public static function validateUrl($url, array $options, Model $model): bool
    {
        if (!str_starts_with($url, 'https')) {
            $model->getErrors()->add('https:// only', ['field' => 'url']);

            return false;
        }

        if (str_contains($url, 'invoiced.com')) {
            $model->getErrors()->add('Cannot use domains with invoiced.com', ['field' => 'url']);

            return false;
        }

        return true;
    }
}
