<?php

namespace App\Chasing\CustomerChasing\Actions;

use App\Chasing\Models\ChasingCadenceStep;

/**
 * Chasing action to perform a call reminder
 * on open accounts.
 */
class PhoneCall extends AbstractTaskAction
{
    public function getTaskAction(): string
    {
        return ChasingCadenceStep::ACTION_PHONE;
    }
}
