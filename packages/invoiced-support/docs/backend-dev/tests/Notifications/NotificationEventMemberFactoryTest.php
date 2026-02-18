<?php

namespace App\Tests\Notifications;

use App\Companies\Models\Member;
use App\Companies\Models\Role;
use App\Core\Authentication\Models\User;
use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Enums\NotificationFrequency;
use App\Notifications\Libs\NotificationEventMemberFactory;
use App\Notifications\Models\NotificationEventSetting;
use App\Notifications\Models\NotificationSubscription;
use App\Tests\AppTestCase;
use App\Tests\Companies\Models\MemberTest;

class NotificationEventMemberFactoryTest extends AppTestCase
{
    private function getFactory(): NotificationEventMemberFactory
    {
        return new NotificationEventMemberFactory(self::getService('test.database'));
    }

    public function testCustomerNotification(): void
    {
        self::hasCompany();
        self::hasCustomer();
        $oldCustomer = self::$customer;
        self::hasCustomer();
        $customer = self::$customer;
        $member = Member::queryWithCurrentTenant()->one();
        $member->notifications = true;
        $member->saveOrFail();

        $factory = $this->getFactory();

        $employee = MemberTest::createUser(time().'employeedelete@example.com');
        $member = $this->createMember($employee);

        NotificationEventSetting::query()->delete();

        /** @var Member[] $allMembers */
        $allMembers = Member::execute();
        $initial = array_map(fn (Member $member) => $member->id, $allMembers);

        $this->assertEquals([], $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));

        $settings = new NotificationEventSetting();
        $settings->member = $allMembers[0];
        $settings->setFrequency(NotificationFrequency::Instant);
        $settings->setNotificationType(NotificationEventType::SubscriptionExpired);
        $settings->saveOrFail();

        $settings = new NotificationEventSetting();
        $settings->member = $allMembers[1];
        $settings->setFrequency(NotificationFrequency::Instant);
        $settings->setNotificationType(NotificationEventType::SubscriptionExpired);
        $settings->saveOrFail();

        $this->assertEquals($initial, $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));

        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];
        $member->saveOrFail();
        $this->assertEquals([$initial[0]], $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));

        $customer->metadata = (object) ['territory' => 'Texas'];
        $customer->saveOrFail();
        $this->assertEquals($initial, $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));

        $member->restriction_mode = Member::OWNER_RESTRICTION;
        $member->saveOrFail();
        $this->assertEquals([$initial[0]], $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));

        $customer->owner = $allMembers[0]->user();
        $customer->saveOrFail();
        $this->assertEquals([$initial[0]], $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));

        $customer->owner = $member->user();
        $customer->saveOrFail();
        $this->assertEquals($initial, $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));

        $subscription1 = new NotificationSubscription();
        $subscription1->customer = self::$customer;
        $subscription1->member = $allMembers[0];
        $subscription1->subscribe = false;
        $subscription1->saveOrFail();
        $subscription2 = new NotificationSubscription();
        $subscription2->customer = $oldCustomer;
        $subscription2->member = $allMembers[1];
        $subscription2->subscribe = false;
        $subscription2->saveOrFail();
        $this->assertEquals([$allMembers[1]->id], $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));

        $allMembers[0]->subscribe_all = false;
        $allMembers[0]->saveOrFail();
        $subscription1->subscribe = true;
        $subscription1->saveOrFail();
        $allMembers[1]->subscribe_all = false;
        $allMembers[1]->saveOrFail();
        $this->assertEquals([$allMembers[0]->id], $factory->getMemberIds(NotificationEventType::SubscriptionExpired, self::$customer->id));
    }

    private function createMember(User $user): Member
    {
        $member = new Member();
        $member->setUser($user);
        $member->role = Role::ADMINISTRATOR;
        $member->notifications = true;
        $member->saveOrFail();

        return $member;
    }
}
