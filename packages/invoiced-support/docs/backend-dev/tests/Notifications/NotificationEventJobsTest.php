<?php

namespace App\Tests\Notifications;

use App\Companies\Models\Member;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Queue\Queue;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\CronJob\AbstractNotificationEventJob;
use App\EntryPoint\CronJob\NotificationEventDailyJob;
use App\EntryPoint\CronJob\NotificationEventInstantJob;
use App\EntryPoint\CronJob\NotificationEventWeeklyJob;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\Models\NotificationRecipient;
use App\Tests\AppTestCase;

class NotificationEventJobsTest extends AppTestCase
{
    public function testJobs(): void
    {
        self::hasCompany();
        /** @var Member $member */
        $member = Member::query()->oneOrNull();
        $connection = self::getService('test.database');
        $queue = \Mockery::mock(Queue::class);
        $queue->shouldNotReceive('enqueue');
        $statsd = \Mockery::mock(StatsdClient::class);
        $statsd->shouldReceive('updateStats');
        $run = \Mockery::mock(Run::class);
        $run->shouldReceive('writeOutput');

        /** @var AbstractNotificationEventJob[] $jobs */
        $jobs = [
            new NotificationEventInstantJob($queue, $connection),
            new NotificationEventDailyJob($queue, $connection),
            new NotificationEventWeeklyJob($queue, $connection),
        ];

        foreach ($jobs as $job) {
            $job->setStatsd($statsd);
            $job->execute($run);
        }

        $event = new NotificationEvent();
        $event->type = 1;
        $event->object_id = 1;
        $event->saveOrFail();

        $recepient = new NotificationRecipient();
        $recepient->member = $member;
        $recepient->notification_event = $event;
        $recepient->saveOrFail();

        foreach ($jobs as $job) {
            $queue->shouldNotReceive('enqueue')->once();
            $job->execute($run);
        }

        $recepient->sent = true;
        $recepient->saveOrFail();
        $queue->shouldNotReceive('enqueue');
        foreach ($jobs as $job) {
            $job->execute($run);
        }
    }
}
