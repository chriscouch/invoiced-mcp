<?php

namespace App\Tests\Notifications\Jobs;

use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\QueueJob\NotificationEventJob;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Libs\NotificationEventMemberFactory;
use App\Notifications\Models\NotificationEventSetting;
use App\Notifications\Models\NotificationRecipient;
use App\Notifications\Models\NotificationSubscription;
use App\Tests\AppTestCase;

class NotificationEventJobTest extends AppTestCase
{
    public static User $user2;

    public function setUp(): void
    {
        parent::setUp();
        self::hasCompany();
        self::hasCustomer();

        self::$user2 = new User();
        self::$user2->create([
            'first_name' => 'Bob2',
            'last_name' => 'Loblaw2',
            'email' => 'test7@example.com',
            'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
            'ip' => '127.0.0.1',
        ]);
        self::$user2->saveOrFail();
    }

    public function tearDown(): void
    {
        self::$user2->delete();
    }

    public function testPerform(): void
    {
        $member1 = Member::query()->one();
        $member1->role = Role::ADMINISTRATOR;
        $member1->saveOrFail();

        $member2 = new Member();
        $member2->role = Role::ADMINISTRATOR;
        $member2->setUser(self::$user2);
        $member2->saveOrFail();

        $member1->notifications = false;
        $member1->saveOrFail();
        $member2->notifications = false;
        $member2->saveOrFail();
        $connection = self::getService('test.database');

        $factory = new NotificationEventMemberFactory($connection);

        $job = new NotificationEventJob($connection, $factory);
        $job->setStatsd(new StatsdClient());

        $job->args = [
            'type' => NotificationEventType::ThreadAssigned->value,
            'objectId' => 1,
            'contextId' => $member1->id,
        ];
        NotificationEventSetting::query()->delete();
        $setting = new NotificationEventSetting();
        $setting->setNotificationType(NotificationEventType::ThreadAssigned);
        $setting->setFrequency(NotificationFrequency::Instant);
        $setting->member = $member1;
        $setting->saveOrFail();

        $setting = new NotificationEventSetting();
        $setting->setNotificationType(NotificationEventType::ThreadAssigned);
        $setting->setFrequency(NotificationFrequency::Instant);
        $setting->member = $member2;
        $setting->saveOrFail();

        $setting = new NotificationEventSetting();
        $setting->setNotificationType(NotificationEventType::EmailReceived);
        $setting->setFrequency(NotificationFrequency::Instant);
        $setting->member = $member1;
        $setting->saveOrFail();

        $setting = new NotificationEventSetting();
        $setting->setNotificationType(NotificationEventType::EmailReceived);
        $setting->setFrequency(NotificationFrequency::Instant);
        $setting->member = $member2;
        $setting->saveOrFail();

        $job->perform();
        $this->assertCount(1, NotificationRecipient::where('member_id', $member1->id)->all());
        NotificationRecipient::query()->delete();

        $job->args = [
            'type' => NotificationEventType::EmailReceived->value,
            'objectId' => 1,
            'contextId' => self::$customer->id,
        ];
        $job->perform();
        $this->assertCount(0, NotificationRecipient::query()->all());

        $member1->notifications = true;
        $member1->saveOrFail();
        $member2->notifications = true;
        $member2->saveOrFail();
        $job->perform();
        $this->assertCount(2, NotificationRecipient::query()->all());
        NotificationRecipient::query()->delete();

        $member1->subscribe_all = false;
        $member1->saveOrFail();
        $job->perform();
        $this->assertCount(1, NotificationRecipient::query()->all());
        NotificationRecipient::query()->delete();

        $subscription = new NotificationSubscription();
        $subscription->customer = self::$customer;
        $subscription->member = $member2;
        $subscription->subscribe = false;
        $subscription->saveOrFail();
        $job->perform();
        $this->assertCount(0, NotificationRecipient::query()->all());

        $subscription = new NotificationSubscription();
        $subscription->customer = self::$customer;
        $subscription->member = $member1;
        $subscription->subscribe = true;
        $subscription->saveOrFail();
        $job->perform();
        $this->assertCount(1, NotificationRecipient::query()->all());
        NotificationRecipient::query()->delete();

        $member1->restriction_mode = Member::OWNER_RESTRICTION;
        $member1->saveOrFail();
        $job->perform();
        $this->assertCount(0, NotificationRecipient::query()->all());
    }
}
