<?php

namespace App\Core\Orm\Traits;

use App\Core\Orm\Event\AbstractEvent;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Property;
use App\Core\Orm\Type;

/**
 * Installs `created_at` and `updated_at` properties on the model.
 *
 * @property int $created_at
 * @property int $updated_at
 */
trait AutoTimestamps
{
    protected function autoInitializeAutoTimestamps(): void
    {
        self::saving([static::class, 'setAutoTimestamps']);
    }

    protected static function autoDefinitionAutoTimestamps(): array
    {
        return [
            'created_at' => new Property(
                type: Type::DATE_UNIX,
                validate: 'timestamp|db_timestamp',
            ),
            'updated_at' => new Property(
                type: Type::DATE_UNIX,
                validate: 'timestamp|db_timestamp',
            ),
        ];
    }

    public static function setAutoTimestamps(AbstractEvent $event): void
    {
        $model = $event->getModel();

        if ($event instanceof ModelCreating) {
            $model->created_at = time(); /* @phpstan-ignore-line */
        }

        $model->updated_at = time(); /* @phpstan-ignore-line */
    }
}
