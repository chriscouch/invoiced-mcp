<?php

namespace App\Core\Orm\Event;

final class ModelUpdating extends AbstractEvent
{
    public static function getName(): string
    {
        return 'model.updating';
    }
}
