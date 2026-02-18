<?php

namespace App\Core\Queue\Events;

abstract class AbstractEnqueueEvent
{
    public function __construct(private string $class, private array $args, private string $queue, private string $id)
    {
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
