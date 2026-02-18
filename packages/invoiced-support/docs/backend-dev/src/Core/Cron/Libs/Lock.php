<?php

namespace App\Core\Cron\Libs;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class Lock
{
    private string $name;
    private LockInterface $lock;

    public function __construct(private string $jobId, private LockFactory $factory, string $namespace = '')
    {
        if ($namespace) {
            $this->name = $namespace.':cron.'.$this->jobId;
        } else {
            $this->name = 'cron.'.$this->jobId;
        }
    }

    /**
     * Checks if this instance has the lock.
     */
    public function hasLock(): bool
    {
        return isset($this->lock) ? $this->lock->isAcquired() : false;
    }

    /**
     * Attempts to acquire the global lock for this job.
     *
     * @param int $expires time in seconds after which the lock expires
     */
    public function acquire(int $expires): bool
    {
        // do not lock if expiry time is 0
        if ($expires <= 0) {
            return true;
        }

        $k = $this->getName();
        $this->lock = $this->factory->createLock($k, (float) $expires);

        return $this->lock->acquire();
    }

    /**
     * Releases the lock.
     */
    public function release(): void
    {
        if (isset($this->lock)) {
            $this->lock->release();
        }
    }

    /**
     * Gets the name of the global lock for this job.
     */
    public function getName(): string
    {
        return $this->name;
    }
}
