<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\PaymentSourceMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class PaymentSourceMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = PaymentSourceMessage::class;

    public function testPaymentSourceCreatedCard(): void
    {
        $message = $this->getMessage(EventType::PaymentSourceCreated->value, ['id' => 1234, 'object' => 'card', 'last4' => '1234', 'brand' => 'Visa']);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('card', 'Visa *1234', 1234),
            new AttributedString(' payment method added for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentSourceCreatedBankAccount(): void
    {
        $message = $this->getMessage(EventType::PaymentSourceCreated->value, ['id' => 1234, 'object' => 'bank_account', 'last4' => '1234', 'bank_name' => 'Chase']);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('bank_account', 'Chase *1234', 1234),
            new AttributedString(' payment method added for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentSourceUpdated(): void
    {
        $message = $this->getMessage(EventType::PaymentSourceUpdated->value, ['id' => 1234, 'object' => 'card', 'last4' => '1234', 'brand' => 'Visa']);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('card', 'Visa *1234', 1234),
            new AttributedString(' payment method updated for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentSourceUpdatedVerified(): void
    {
        $message = $this->getMessage(EventType::PaymentSourceUpdated->value, ['id' => 1234, 'object' => 'card', 'last4' => '1234', 'brand' => 'Visa', 'verified' => true], [], ['verified' => false]);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('card', 'Visa *1234', 1234),
            new AttributedString(' payment method was verified for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentSourceDeleted(): void
    {
        $message = $this->getMessage(EventType::PaymentSourceDeleted->value, ['id' => 1234, 'object' => 'card', 'last4' => '1234', 'brand' => 'Visa']);
        $message->setCustomer(self::$customer);
        $expected = [
            new AttributedObject('card', 'Visa *1234', 1234),
            new AttributedString(' payment method removed for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::PaymentSourceCreated->value, ['id' => 1234, 'object' => 'card', 'last4' => '1234', 'brand' => 'Visa']);
        $message->setCustomer(self::$customer);

        $this->assertEquals('<b>Visa *1234</b> payment method added for <b>Sherlock</b>', (string) $message);
    }
}
