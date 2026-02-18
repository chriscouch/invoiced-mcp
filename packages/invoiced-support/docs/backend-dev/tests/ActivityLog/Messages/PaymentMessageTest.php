<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\PaymentMessage;
use App\ActivityLog\ValueObjects\AttributedMoneyAmount;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\PaymentProcessing\Models\PaymentMethod;

class PaymentMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = PaymentMessage::class;

    public function testPaymentCreated(): void
    {
        $object = [
            'customer' => [],
            'currency' => 'usd',
            'amount' => 100,
            'method' => PaymentMethod::CREDIT_CARD,
        ];
        $message = $this->getMessage(EventType::PaymentCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setPayment(self::$payment);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' paid '),
            new AttributedObject('payment', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -12),
            new AttributedString(' via credit card'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentUpdated(): void
    {
        $object = [
            'customer' => [],
            'currency' => 'usd',
            'amount' => 100,
            'method' => PaymentMethod::WIRE_TRANSFER,
        ];
        $message = $this->getMessage(EventType::PaymentUpdated->value, $object);
        $message->setCustomer(self::$customer)
            ->setPayment(self::$payment);

        $expected = [
            new AttributedObject('payment', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -12),
            new AttributedString(' wire transfer '),
            new AttributedString(' from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentDeleted(): void
    {
        $object = [
            'customer' => [],
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::PaymentDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('A payment for '),
            new AttributedObject('payment', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), null),
            new AttributedString(' from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was voided'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [
            'customer' => [],
            'currency' => 'usd',
            'amount' => 100,
            'method' => PaymentMethod::CHECK,
        ];
        $message = $this->getMessage(EventType::PaymentCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setPayment(self::$payment);

        $this->assertEquals('<b>Sherlock</b> paid <b>$100.00</b> via check', (string) $message);
    }
}
