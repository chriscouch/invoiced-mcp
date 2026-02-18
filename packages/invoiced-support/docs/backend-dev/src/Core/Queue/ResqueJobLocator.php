<?php

namespace App\Core\Queue;

use App\Core\Queue\Interfaces\ResqueJobInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Uses the service locator pattern to get instances of
 * Resque job classes from the Symfony service container.
 */
class ResqueJobLocator
{
    public function __construct(private ServiceLocator $serviceLocator)
    {
    }

    public function getJob(string $class): ResqueJobInterface
    {
        return $this->serviceLocator->get($class);
    }
}
