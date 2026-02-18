<?php

namespace App\Core\Queue;

use App\Core\Queue\Interfaces\ResqueJobInterface;

abstract class AbstractResqueJob implements ResqueJobInterface
{
    public array $args = [];
    public string $queue = '';

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }
}
