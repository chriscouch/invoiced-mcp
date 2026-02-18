<?php

namespace App\Tests\Notifications;

use App\Companies\Models\Member;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\QueueJob\SendNotificationsJob;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Libs\NotificationEmailFactory;
use App\Notifications\Libs\NotificationEmailSender;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\Models\NotificationEventCompanySetting;
use App\Notifications\Models\NotificationEventSetting;
use App\Notifications\Models\NotificationRecipient;
use App\Tests\AppTestCase;
use Mockery;

class SendNotificationsJobTest extends AppTestCase
{
    private static Member $member;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$member = Member::query()->one();
    }

    public function testPerform(): void
    {
        $member = self::$member;
        NotificationEventSetting::query()->delete();
        $tenant = self::getService('test.tenant');
        $tenant->set(self::$company);
        $statsd = Mockery::mock(StatsdClient::class);
        $factory = Mockery::mock(NotificationEmailFactory::class);
        $statsd->shouldReceive('increment');
        $sender = Mockery::mock(NotificationEmailSender::class);
        $sender->shouldReceive('send')->once();
        $job = new SendNotificationsJob($factory, $sender);
        $job->setStatsd($statsd);

        $job->args = ['member_id' => $member->id];
        $job->perform();

        $job->args = ['member_id' => $member->id, 'frequency' => NotificationFrequency::Instant->value];
        $job->perform();

        $settings = new NotificationEventSetting();
        $settings->member = $member;
        $settings->setFrequency(NotificationFrequency::Weekly);
        $settings->setNotificationType(NotificationEventType::SubscriptionExpired);
        $settings->saveOrFail();
        $job->perform();

        $settings->setFrequency(NotificationFrequency::Daily);
        $settings->saveOrFail();
        $job->perform();

        $factory->shouldReceive('getEvents')->withArgs(fn ($arg1, $arg2) => 6 == $arg1 && $arg2->id == $member->id)->andReturn([new NotificationEvent()])->once();
        $factory->shouldReceive('build')->withArgs(fn ($arg1) => 6 == $arg1)->once();

        $settings->setFrequency(NotificationFrequency::Instant);
        $settings->setNotificationType(NotificationEventType::PaymentDone);
        $settings->saveOrFail();
        $job->perform();
    }

    public function testRole(): void
    {
        $settings = new NotificationEventSetting();
        $settings->member = self::$member;
        $settings->setFrequency(NotificationFrequency::Instant);
        $settings->setNotificationType(NotificationEventType::SubscriptionExpired);
        $settings->saveOrFail();

        $job = self::getService('test.send_notifications_job');
        $job->args = ['member_id' => self::$member->id, 'frequency' => NotificationFrequency::Instant->value];
        $statsd = Mockery::mock(StatsdClient::class);
        $job->setStatsd($statsd);
        $statsd->shouldReceive('increment')
            ->withArgs(['user_notification.sent', 1.0, ['notification' => NotificationEventType::SubscriptionExpired->value]])
            ->twice();
        $event = new NotificationEvent();
        $event->setType(NotificationEventType::SubscriptionExpired);
        $event->object_id = 1;
        $event->saveOrFail();

        $recipient = new NotificationRecipient();
        $recipient->member = self::$member;
        $recipient->notification_event = $event;
        $recipient->sent = false;
        $recipient->saveOrFail();
        $job->perform();

        $role = self::$member->role();
        $role->notifications_edit = false;
        $role->saveOrFail();
        $recipient->sent = false;
        $recipient->saveOrFail();
        $job->perform();

        /** @var NotificationEventCompanySetting[] $notificationCompany */
        $notificationCompany = NotificationEventCompanySetting::all();
        foreach ($notificationCompany as $item) {
            $item->setFrequency(NotificationFrequency::Daily);
            $item->save();
        }
        $recipient->sent = false;
        $recipient->saveOrFail();
        $job->perform();
    }
}
