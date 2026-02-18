<?php

namespace App\Chasing\CustomerChasing\Actions;

/**
 * Chasing action to internally escalate
 * open accounts for review.
 */
class Escalate extends AbstractTaskAction
{
    public function limitOncePerRun(): bool
    {
        // Multiple escalations within a single chasing run is permitted.
        return false;
    }

    public function getTaskAction(): string
    {
        return 'review';
    }
}
