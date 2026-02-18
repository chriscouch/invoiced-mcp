<?php

namespace App\AccountsReceivable\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Type;

/**
 * @property string $name
 */
class ContactRole extends MultitenantModel
{
    use AutoTimestamps;

    protected static function getProperties(): array
    {
        return [
            'name' => new Property(
                type: Type::STRING,
            ),
        ];
    }

    protected function initialize(): void
    {
        self::deleting([self::class, 'verifyDelete']);
        parent::initialize();
    }

    public static function verifyDelete(AbstractEvent $event): void
    {
        $inUse = Contact::where('role_id', $event->getModel()->id())->count() > 0;
        if ($inUse) {
            throw new ListenerException('Cannot delete role because it is being used by contacts.');
        }
    }
}
