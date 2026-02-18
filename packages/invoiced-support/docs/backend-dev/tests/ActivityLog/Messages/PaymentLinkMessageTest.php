<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\PaymentLinkMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class PaymentLinkMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = PaymentLinkMessage::class;

    public function testPaymentLinkCreated(): void
    {
        $message = $this->getMessage(EventType::PaymentLinkCreated->value);
        $message->setPaymentLink(self::$paymentLink);

        $expected = [
            new AttributedObject('payment_link', 'Payment Link', -16),
            new AttributedString(' was created'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentLinkUpdated(): void
    {
        $message = $this->getMessage(EventType::PaymentLinkUpdated->value);
        $message->setPaymentLink(self::$paymentLink);

        $expected = [
            new AttributedObject('payment_link', 'Payment Link', -16),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentLinkDeleted(): void
    {
        $message = $this->getMessage(EventType::PaymentLinkDeleted->value);
        $message->setPaymentLink(self::$paymentLink);
        $expected = [
            new AttributedObject('payment_link', 'Payment Link', -16),
            new AttributedString(' was deleted'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentLinkCompleted(): void
    {
        $message = $this->getMessage(EventType::PaymentLinkCompleted->value);
        $message->setPaymentLink(self::$paymentLink);
        $expected = [
            new AttributedObject('payment_link', 'Payment Link', -16),
            new AttributedString(' was completed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::PaymentLinkCreated->value);
        $message->setPaymentLink(self::$paymentLink);

        $this->assertEquals('<b>Payment Link</b> was created', (string) $message);
    }
}
