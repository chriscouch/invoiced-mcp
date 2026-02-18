<?php

namespace App\Core\Orm\Event;

use App\Core\Orm\Model;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractEvent extends Event
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Gets the model for this event.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    abstract public static function getName(): string;
}
