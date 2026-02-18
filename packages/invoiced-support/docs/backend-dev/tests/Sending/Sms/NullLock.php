<?php

namespace App\Tests\Sending\Sms;

use Symfony\Component\Lock\LockInterface;

class NullLock implements LockInterface
{
    public function acquire(bool $blocking = false): bool
    {
        return true;
    }

    public function refresh(?float $ttl = null): void
    {
    }

    public function isAcquired(): bool
    {
        return true;
    }

    public function release(): bool
    {
        return true;
    }

    public function isExpired(): bool
    {
        return false;
    }

    public function getRemainingLifetime(): float
    {
        return 0;
    }
}
