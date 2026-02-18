<?php

namespace App\Tokenization\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Utils\RandomString;

/**
 * @property string      $secret
 */
class PublishableKey extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'secret' => new Property(
                required: true,
            ),
        ];
    }

    public function setSecret(): void
    {
       $this->secret = ($this->tenant()->country ?? 'US') . RandomString::generate(30);
    }
}
