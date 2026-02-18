<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\SubscriptionMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;

class SubscriptionMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = SubscriptionMessage::class;

    public function testSubscriptionCreated(): void
    {
        $message = $this->getMessage(EventType::SubscriptionCreated->value);
        $message->setCustomer(self::$customer)
            ->setPlan(self::$plan);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' subscribed to '),
            new AttributedObject('plan', 'Starter', 'test_plan'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testSubscriptionUpdated(): void
    {
        $message = $this->getMessage(EventType::SubscriptionUpdated->value);
        $message->setCustomer(self::$customer)
            ->setPlan(self::$plan);

        $expected = [
            new AttributedString('Subscription to '),
            new AttributedObject('plan', 'Starter', 'test_plan'),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testSubscriptionUpdatedChangedPlan(): void
    {
        $previous = ['plan' => 'test_plan'];
        $object = ['plan' => ['id' => 'new_plan']];
        $message = $this->getMessage(EventType::SubscriptionUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setPlan(self::$plan);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' switched plans from "test_plan" to "new_plan"'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testSubscriptionUpdatedRenewed(): void
    {
        $previous = ['renewed_last' => null];
        $message = $this->getMessage(EventType::SubscriptionUpdated->value, [], [], $previous);
        $message->setCustomer(self::$customer)
            ->setPlan(self::$plan);

        $expected = [
            new AttributedString('Subscription to '),
            new AttributedObject('plan', 'Starter', 'test_plan'),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was renewed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testSubscriptionUpdatedCancelAtPeriodEnd(): void
    {
        $previous = ['cancel_at_period_end' => false];
        $message = $this->getMessage(EventType::SubscriptionUpdated->value, [], [], $previous);
        $message->setCustomer(self::$customer)
            ->setPlan(self::$plan);

        $expected = [
            new AttributedString('Subscription to '),
            new AttributedObject('plan', 'Starter', 'test_plan'),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' will be canceled at end of billing period'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testSubscriptionUpdatedChangedStatus(): void
    {
        $previous = ['status' => SubscriptionStatus::ACTIVE];
        $object = ['status' => SubscriptionStatus::PAST_DUE];
        $message = $this->getMessage(EventType::SubscriptionUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setPlan(self::$plan);

        $expected = [
            new AttributedString('Subscription to '),
            new AttributedObject('plan', 'Starter', 'test_plan'),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' went from "Active" to "Past Due"'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testSubscriptionDeleted(): void
    {
        $message = $this->getMessage(EventType::SubscriptionCanceled->value);
        $message->setCustomer(self::$customer)
            ->setPlan(self::$plan);
        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' canceled their subscription to '),
            new AttributedObject('plan', 'Starter', 'test_plan'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::SubscriptionCreated->value);
        $message->setCustomer(self::$customer)
            ->setPlan(self::$plan);

        $this->assertEquals('<b>Sherlock</b> subscribed to <b>Starter</b>', (string) $message);
    }
}
