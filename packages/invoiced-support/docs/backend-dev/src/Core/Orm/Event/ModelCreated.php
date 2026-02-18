<?php

namespace App\Core\Orm\Event;

final class ModelCreated extends AbstractEvent
{
    public static function getName(): string
    {
        return 'model.created';
    }
}
