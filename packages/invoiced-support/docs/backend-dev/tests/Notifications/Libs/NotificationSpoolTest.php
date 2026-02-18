<?php

namespace App\Tests\Notifications\Libs;

use App\Core\Queue\Queue;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Notifications\Models\NotificationEvent;
use App\Tests\AppTestCase;
use Carbon\Carbon;

class NotificationSpoolTest extends AppTestCase
{
    public function testSpool(): void
    {
        self::hasCompany();

        $queue = \Mockery::mock(Queue::class);
        $spool = new NotificationSpool($queue);

        $spool->spool(NotificationEventType::SubscriptionExpired, self::$company->id, 1, 1);

        $queue->shouldReceive('enqueue')->once();
        $this->assertEquals(1, $spool->size());
        $spool->flush();

        $event = new NotificationEvent();
        $event->setType(NotificationEventType::SubscriptionExpired);
        $event->object_id = 1;
        $event->saveOrFail();

        $queue->shouldNotReceive('enqueue');
        $spool->spool(NotificationEventType::SubscriptionExpired, self::$company->id, 1, 1);
        $this->assertEquals(0, $spool->size());
        $spool->flush();

        $spool->spool(NotificationEventType::SubscriptionExpired, self::$company->id, 2, 1);
        $spool->spool(NotificationEventType::EmailReceived, self::$company->id, 1, 1);
        $this->assertEquals(2, $spool->size());

        $event->created_at = Carbon::now()->subDay()->getTimestamp();
        $event->saveOrFail();
        $spool->spool(NotificationEventType::SubscriptionExpired, self::$company->id, 1, 1);
        $this->assertEquals(3, $spool->size());

        $queue->shouldReceive('enqueue')->times(3);
        $spool->flush();

        // this should not flush
        $spool->spool(NotificationEventType::InvoiceViewed, self::$company->id, 1, 1);
        $this->assertEquals(1, $spool->size());
        $spool->clear();
        $this->assertEquals(0, $spool->size());
        $spool->flush();
    }
}
