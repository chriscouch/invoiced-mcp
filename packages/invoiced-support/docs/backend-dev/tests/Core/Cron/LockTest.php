<?php

namespace App\Tests\Core\Cron;

use App\Core\Cron\Libs\Lock;
use App\Tests\AppTestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class LockTest extends AppTestCase
{
    private static LockFactory $lockFactory;

    public static function setUpBeforeClass(): void
    {
        $store = new FlockStore(sys_get_temp_dir());
        self::$lockFactory = new LockFactory($store);
    }

    public function testGetName(): void
    {
        $lock = new Lock('my_job', self::$lockFactory);
        $this->assertEquals('cron.my_job', $lock->getName());
        $lock = new Lock('my_job', self::$lockFactory, 'namespaced');
        $this->assertEquals('namespaced:cron.my_job', $lock->getName());
    }

    public function testAcquireNoExpiry(): void
    {
        $lock = new Lock('my_job', self::$lockFactory);

        $this->assertFalse($lock->hasLock());
        $this->assertTrue($lock->acquire(0));
        $this->assertFalse($lock->hasLock());
    }

    public function testAcquire(): void
    {
        $lock = new Lock('my_job', self::$lockFactory);
        $this->assertTrue($lock->acquire(100));
        $this->assertTrue($lock->hasLock());

        $lock->release();
        $this->assertFalse($lock->hasLock());
    }

    public function testAcquireNamespace(): void
    {
        $lock1 = new Lock('test', self::$lockFactory);
        $this->assertTrue($lock1->acquire(100));

        $lock2 = new Lock('test', self::$lockFactory, 'namespaced');
        $this->assertTrue($lock2->acquire(100));
        $this->assertTrue($lock1->hasLock());
        $this->assertTrue($lock2->hasLock());
    }
}
