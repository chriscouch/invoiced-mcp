<?php

namespace App\Sending\Email\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Utils\RandomString;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * An Inbox represents a central place where EmailThreads reside.
 *
 * @property int    $id
 * @property string $external_id
 */
class Inbox extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'external_id' => new Property(
                type: Type::STRING,
                required: true,
                validate: ['string', 'min' => 10, 'max' => 10],
            ),
        ];
    }

    protected function initialize(): void
    {
        self::creating([self::class, 'setId']);

        parent::initialize();
    }

    //
    // Hooks
    //

    public static function setId(AbstractEvent $event): void
    {
        /** @var self $model */
        $model = $event->getModel();
        $model->external_id = RandomString::generate(10, 'abcdefghijklmnopqrstuvwxyz1234567890');
    }
}
