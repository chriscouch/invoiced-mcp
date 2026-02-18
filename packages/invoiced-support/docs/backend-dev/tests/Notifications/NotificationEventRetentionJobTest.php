<?php

namespace App\Tests\Notifications;

use App\Companies\Models\Member;
use App\Core\Cron\ValueObjects\Run;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\Models\NotificationRecipient;
use App\Tests\AppTestCase;
use Carbon\Carbon;

class NotificationEventRetentionJobTest extends AppTestCase
{
    public function testExecute(): void
    {
        self::hasCompany();

        $this->assertEquals(0, NotificationEvent::query()->count());
        $this->assertEquals(0, NotificationRecipient::query()->count());

        /** @var Member $member */
        $member = Member::query()->oneOrNull();

        $event = new NotificationEvent();
        $event->setType(NotificationEventType::SubscriptionExpired);
        $event->object_id = 1;
        $event->setType(NotificationEventType::EmailReceived);
        $event->saveOrFail();

        $event->created_at = Carbon::today()->subDays(91)->getTimestamp();
        $event->saveOrFail();

        $recepient = new NotificationRecipient();
        $recepient->member = $member;
        $recepient->notification_event = $event;
        $recepient->saveOrFail();

        $event = new NotificationEvent();
        $event->setType(NotificationEventType::SubscriptionExpired);
        $event->setType(NotificationEventType::EmailReceived);
        $event->object_id = 1;
        $event->saveOrFail();

        $recepient = new NotificationRecipient();
        $recepient->member = $member;
        $recepient->notification_event = $event;
        $recepient->saveOrFail();

        $this->assertEquals(2, NotificationEvent::query()->count());
        $this->assertEquals(2, NotificationRecipient::query()->count());

        $job = self::getService('test.garbage_collection_job');

        $job->execute(new Run());

        $events = NotificationEvent::query()->execute();
        $recepients = NotificationRecipient::query()->execute();
        $this->assertCount(1, $events);
        $this->assertCount(1, $recepients);
        $this->assertEquals($event->toArray(), $events[0]->toArray());
        $this->assertEquals($recepient->toArray(), $recepients[0]->toArray());
    }
}
