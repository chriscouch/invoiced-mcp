<?php

namespace App\Chasing\CustomerChasing\Actions;

use App\Chasing\Interfaces\ActionInterface;

abstract class AbstractAction implements ActionInterface
{
    public function limitOncePerRun(): bool
    {
        return true;
    }
}
