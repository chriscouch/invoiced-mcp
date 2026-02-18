<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\Chasing\CustomerChasing\CustomerCadenceLock;
use App\Chasing\Models\ChasingCadence;
use App\Tests\AppTestCase;

class ChasingLockTest extends AppTestCase
{
    public function testGetName(): void
    {
        $cadence = new ChasingCadence(['id' => 10]);
        $lock = new CustomerCadenceLock($cadence, self::getService('test.lock_factory'), 'test');

        $this->assertEquals('test:chasing_lock.chasing_cadence.10', $lock->getName());
    }

    public function testAcquireNoExpiry(): void
    {
        $cadence = new ChasingCadence(['id' => 10]);
        $lock = new CustomerCadenceLock($cadence, self::getService('test.lock_factory'), 'test');

        $this->assertFalse($lock->hasLock());
        $this->assertTrue($lock->acquire(0));
        $this->assertFalse($lock->hasLock());
    }

    public function testAcquire(): void
    {
        $cadence = new ChasingCadence(['id' => 10]);
        $lock = new CustomerCadenceLock($cadence, self::getService('test.lock_factory'), 'test');

        $this->assertTrue($lock->acquire(100));
        $this->assertTrue($lock->hasLock());

        $lock->release();
        $this->assertFalse($lock->hasLock());
    }

    public function testAcquireAlreadyLocked(): void
    {
        // lock the ChasingCadence
        self::getService('test.redis')->setex('test:chasing_lock.chasing_cadence.10', 1, true);

        $cadence = new ChasingCadence(['id' => 10]);
        $lock = new CustomerCadenceLock($cadence, self::getService('test.lock_factory'), 'test');

        // should not be able to acquire lock
        $this->assertFalse($lock->acquire(100));
        $this->assertFalse($lock->hasLock());

        // after 2s the lock should be available
        sleep(2);
        $this->assertTrue($lock->acquire(100));
        $this->assertTrue($lock->hasLock());
    }
}
