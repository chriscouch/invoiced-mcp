<?php

namespace App\Tests\Imports;

use App\Companies\Models\Company;
use App\Imports\Libs\ImportLock;
use App\Tests\AppTestCase;

class ImportLockTest extends AppTestCase
{
    public function testGetName(): void
    {
        $company = new Company(['id' => 10]);
        $lock = new ImportLock(self::getService('test.lock_factory'), $company, 'invoice', 'test');

        $this->assertEquals('test:import_lock.10_invoice', $lock->getName());
    }

    public function testAcquireNoExpiry(): void
    {
        $company = new Company(['id' => 10]);
        $lock = new ImportLock(self::getService('test.lock_factory'), $company, 'invoice', 'test');

        $this->assertFalse($lock->hasLock());
        $this->assertTrue($lock->acquire(0));
        $this->assertFalse($lock->hasLock());
    }

    public function testAcquire(): void
    {
        $company = new Company(['id' => 10]);
        $lock = new ImportLock(self::getService('test.lock_factory'), $company, 'invoice', 'test');

        $this->assertTrue($lock->acquire(100));
        $this->assertTrue($lock->hasLock());

        $lock->release();
        $this->assertFalse($lock->hasLock());
    }

    public function testAcquireAlreadyLocked(): void
    {
        // lock the invoice
        self::getService('test.redis')->setex('test:import_lock.10_invoice', 1, true);

        $company = new Company(['id' => 10]);
        $lock = new ImportLock(self::getService('test.lock_factory'), $company, 'invoice', 'test');

        // should not be able to acquire lock
        $this->assertFalse($lock->acquire(100));
        $this->assertFalse($lock->hasLock());

        // after 2s the lock should be available
        sleep(2);
        $this->assertTrue($lock->acquire(100));
        $this->assertTrue($lock->hasLock());
    }
}
