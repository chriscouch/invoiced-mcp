<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\SubscriptionExpired;

class SubscriptionExpiredTest extends AbstractNotificationEmailTest
{
    private array $subscriptions;

    private function addEvent(): void
    {
        self::hasCustomer();
        self::hasSubscription();
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::SubscriptionExpired);
        $event->object_id = self::$subscription->id;
        self::$events[] = $event;
        $subscription = self::$subscription->toArray();
        $subscription['customer'] = self::$customer->toArray();
        $this->subscriptions[] = $subscription;
    }

    public function testProcess(): void
    {
        self::hasPlan();
        $this->addEvent();

        $email = new SubscriptionExpired(self::getService('test.database'));

        $this->assertEquals(
            [
                'subject' => 'Subscription has been canceled due to non payment',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/subscription-expired', $email->getTemplate(self::$events));
        $this->assertEquals($this->subscriptions, $email->getVariables(self::$events)['subscriptions']);
    }

    public function testProcessBulk(): void
    {
        $email = new SubscriptionExpired(self::getService('test.database'));

        $this->addEvent();
        $this->addEvent();
        $this->addEvent();
        $this->assertEquals(
            [
                'subject' => 'Subscription has been canceled due to non payment',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/subscription-expired-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
