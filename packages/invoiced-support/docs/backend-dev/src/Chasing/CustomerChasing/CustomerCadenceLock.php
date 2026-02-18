<?php

namespace App\Chasing\CustomerChasing;

use App\Chasing\Models\ChasingCadence;
use App\Core\Utils\AppUrl;
use App\Core\Utils\ModelLock;
use Symfony\Component\Lock\LockFactory;

/**
 * This class manages locking for chasing runs. The lock can be used
 * to prevent the cadence from being chased concurrently.
 */
final class CustomerCadenceLock extends ModelLock
{
    public function __construct(ChasingCadence $cadence, LockFactory $lockFactory, ?string $namespace = null)
    {
        $namespace ??= AppUrl::get()->getHostname();
        $namespace .= ':chasing_lock.';
        parent::__construct($cadence, $lockFactory, $namespace);
    }
}
