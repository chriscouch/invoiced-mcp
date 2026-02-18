<?php

namespace App\Sending\Sms\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * @property int         $id
 * @property string      $name
 * @property string|null $language
 * @property string      $message
 * @property string      $template_engine
 */
class SmsTemplate extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
            'language' => new Property(
                null: true,
                validate: ['string', 'min' => 2, 'max' => 2],
            ),
            'message' => new Property(
                required: true,
            ),
            'template_engine' => new Property(
                validate: ['enum', 'choices' => ['mustache', 'twig']],
                default: 'twig',
            ),
        ];
    }
}
