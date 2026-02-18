<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\Invoice;
use App\PaymentProcessing\Libs\PaymentLock;
use App\Tests\AppTestCase;

class PaymentLockTest extends AppTestCase
{
    public function testGetName(): void
    {
        $invoice = new Invoice(['id' => 10]);
        $lock = new PaymentLock($invoice, self::getService('test.lock_factory'), 'test');

        $this->assertEquals('test:payment_lock.invoice.10', $lock->getName());
    }

    public function testAcquireNoExpiry(): void
    {
        $invoice = new Invoice(['id' => 10]);
        $lock = new PaymentLock($invoice, self::getService('test.lock_factory'), 'test');

        $this->assertFalse($lock->hasLock());
        $this->assertTrue($lock->acquire(0));
        $this->assertFalse($lock->hasLock());
    }

    public function testAcquire(): void
    {
        $invoice = new Invoice(['id' => 10]);
        $lock = new PaymentLock($invoice, self::getService('test.lock_factory'), 'test');

        $this->assertTrue($lock->acquire(100));
        $this->assertTrue($lock->hasLock());

        $lock->release();
        $this->assertFalse($lock->hasLock());
    }

    public function testAcquireAlreadyLocked(): void
    {
        // lock the invoice
        self::getService('test.redis')->setex('test:payment_lock.invoice.10', 1, true);

        $invoice = new Invoice(['id' => 10]);
        $lock = new PaymentLock($invoice, self::getService('test.lock_factory'), 'test');

        // should not be able to acquire lock
        $this->assertFalse($lock->acquire(100));
        $this->assertFalse($lock->hasLock());

        // after 2s the lock should be available
        sleep(2);
        $this->assertTrue($lock->acquire(100));
        $this->assertTrue($lock->hasLock());
    }
}
