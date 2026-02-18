<?php

namespace App\Chasing\Models;

use App\Core\Multitenant\Models\MultitenantModel;
use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;

/**
 * Abstract model for representing chasing cadences.
 *
 * @property int    $id
 * @property string $name
 */
abstract class AbstractChasingCadence extends MultitenantModel
{
    use AutoTimestamps;

    const CADENCE_LIMIT = 100;

    protected static function autoDefinitionChasingCadence(): array
    {
        return [
            'name' => new Property(
                required: true,
            ),
        ];
    }

    protected function initialize(): void
    {
        parent::initialize();

        self::creating([self::class, 'cadenceLimit']);
    }

    public static function cadenceLimit(AbstractEvent $event): void
    {
        /** @var MultitenantModel $class */
        $class = $event->getModel()::class;
        if ($class::count() > self::CADENCE_LIMIT) {
            throw new ListenerException('You can not create more than '.self::CADENCE_LIMIT.' chasing cadences.');
        }
    }
}
