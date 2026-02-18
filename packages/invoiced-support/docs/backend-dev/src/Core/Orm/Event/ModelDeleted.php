<?php

namespace App\Core\Orm\Event;

final class ModelDeleted extends AbstractEvent
{
    public static function getName(): string
    {
        return 'model.deleted';
    }
}
