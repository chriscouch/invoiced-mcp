<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\PaymentPlanMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class PaymentPlanMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = PaymentPlanMessage::class;

    public function testPaymentPlanCreated(): void
    {
        $message = $this->getMessage(EventType::PaymentPlanCreated->value);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Payment plan for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was created'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentPlanUpdated(): void
    {
        $message = $this->getMessage(EventType::PaymentPlanUpdated->value);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Payment plan for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentPlanDeleted(): void
    {
        $message = $this->getMessage(EventType::PaymentPlanDeleted->value);
        $message->setCustomer(self::$customer);
        $expected = [
            new AttributedString('Payment plan for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was deleted'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::PaymentPlanCreated->value);
        $message->setCustomer(self::$customer);

        $this->assertEquals('Payment plan for <b>Sherlock</b> was created', (string) $message);
    }
}
