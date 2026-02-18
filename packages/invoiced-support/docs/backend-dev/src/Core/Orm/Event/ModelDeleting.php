<?php

namespace App\Core\Orm\Event;

final class ModelDeleting extends AbstractEvent
{
    public static function getName(): string
    {
        return 'model.deleting';
    }
}
