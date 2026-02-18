<?php

namespace App\Core\Orm\Event;

final class ModelCreating extends AbstractEvent
{
    public static function getName(): string
    {
        return 'model.creating';
    }
}
