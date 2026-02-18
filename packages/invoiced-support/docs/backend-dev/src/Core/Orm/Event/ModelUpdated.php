<?php

namespace App\Core\Orm\Event;

final class ModelUpdated extends AbstractEvent
{
    public static function getName(): string
    {
        return 'model.updated';
    }
}
