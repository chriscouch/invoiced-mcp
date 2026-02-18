<?php

namespace App\Tests\AccountsReceivable\Models;

use App\Core\Authentication\Models\User;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\ActivityLog\ValueObjects\PendingUpdateEvent;
use App\Tests\AppTestCase;
use Closure;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class CustomerEventTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $user = new User();
        $user->email = microtime(true).'_event@example.com';
        $user->password = ['TestPassw0rd!', 'TestPassw0rd!']; /* @phpstan-ignore-line */
        $user->ip = '127.0.0.1';
        $user->first_name = 'Bob';
        $user->last_name = 'Loblaw';
        $user->saveOrFail();

        self::getService('test.user_context')->set($user);

        self::hasCompany();
        self::hasCustomer();
        self::hasCard();
    }

    public function tearDown(): void
    {
        EventSpoolFacade::$instance = null;
        EventSpool::disable();
        parent::tearDown();
    }

    public function testPaymentSourceEventAdd(): void
    {
        EventSpool::enable();
        $this->makeSpool(function (PendingUpdateEvent $event) {
            $this->assertEquals(EventType::CustomerUpdated, $event->getType());
            $this->assertNull($event->getPrevious()['payment_source']);

            return true;
        });
        self::$customer->setDefaultPaymentSource(self::$card);
        EventSpoolFacade::$instance = null;
        EventSpool::disable();
    }

    /**
     * @depends testPaymentSourceEventAdd
     */
    public function testPaymentSourceEventUpdate(): void
    {
        self::hasCustomer();
        self::hasCard();
        self::hasBankAccount();

        self::$customer->setDefaultPaymentSource(self::$card);

        $this->makeSpool(function (PendingUpdateEvent $event) {
            $this->assertEquals(EventType::CustomerUpdated, $event->getType());
            $this->assertIsArray($event->getPrevious()['payment_source']);

            return true;
        });

        EventSpool::enable();
        self::$customer->setDefaultPaymentSource(self::$bankAccount);
    }

    /**
     * @depends testPaymentSourceEventAdd
     */
    public function testPaymentSourceEventClear(): void
    {
        self::hasCustomer();
        self::hasCard();

        self::$customer->setDefaultPaymentSource(self::$card);

        $this->makeSpool(function (PendingUpdateEvent $event) {
            $this->assertEquals(EventType::CustomerUpdated, $event->getType());
            $this->assertIsArray($event->getPrevious()['payment_source']);

            return true;
        });

        EventSpool::enable();
        self::$customer->clearDefaultPaymentSource();
    }

    private function makeSpool(Closure $closure): void
    {
        $spool = Mockery::mock(EventSpool::class);
        $facade = new EventSpoolFacade($spool);
        EventSpoolFacade::$instance = $facade;
        $spool->shouldReceive('enqueue')->withArgs($closure)->once();
    }
}
