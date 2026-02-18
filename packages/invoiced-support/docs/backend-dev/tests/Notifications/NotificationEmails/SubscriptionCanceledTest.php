<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Models\NotificationEvent;
use App\Notifications\NotificationEmails\SubscriptionCanceled;

class SubscriptionCanceledTest extends AbstractNotificationEmailTest
{
    private array $subscriptions;

    private function addEvent(): void
    {
        self::hasCustomer();
        self::hasSubscription();
        $event = new NotificationEvent(['id' => -1]);
        $event->setType(NotificationEventType::SubscriptionCanceled);
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

        $email = new SubscriptionCanceled(self::getService('test.database'));

        $this->assertEquals(
            [
                'subject' => 'Subscription has been canceled in customer portal',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/subscription-canceled', $email->getTemplate(self::$events));
        $this->assertEquals($this->subscriptions, $email->getVariables(self::$events)['subscriptions']);
    }

    public function testProcessBulk(): void
    {
        $email = new SubscriptionCanceled(self::getService('test.database'));

        $this->addEvent();
        $this->addEvent();
        $this->addEvent();

        $this->assertEquals(
            [
                'subject' => 'Subscription has been canceled in customer portal',
            ],
            $email->getMessage(self::$events)
        );
        $this->assertEquals('notifications/subscription-canceled-bulk', $email->getTemplate(self::$events));
        $this->assertEquals(
            [
                'count' => 4,
            ],
            $email->getVariables(self::$events)
        );
    }
}
