<?php

namespace App\Chasing\Interfaces;

use App\Chasing\ValueObjects\ActionResult;
use App\Chasing\ValueObjects\ChasingEvent;

/**
 * The interface that all chasing actions must implement.
 */
interface ActionInterface
{
    /**
     * Indicates whether this action can be executed
     * once per run, per customer. If this returns false
     * then this means the action can be executed multiple
     * times within a single chasing run.
     */
    public function limitOncePerRun(): bool;

    /**
     * Executes a chasing step for this action.
     */
    public function execute(ChasingEvent $event): ActionResult;
}
