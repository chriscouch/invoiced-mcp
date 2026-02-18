<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\CustomerMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class CustomerMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = CustomerMessage::class;

    public function testCustomerCreated(): void
    {
        $message = $this->getMessage(EventType::CustomerCreated->value);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was added as a new customer'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerUpdated(): void
    {
        $message = $this->getMessage(EventType::CustomerUpdated->value);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('The profile for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerUpdatedAddedPaymentSource(): void
    {
        $previous = [
            'payment_source' => null,
        ];
        $object = [
            'payment_source' => [
                'id' => -3,
                'object' => 'card',
                'last4' => '1234',
                'brand' => 'Visa',
                'exp_month' => '2',
                'exp_year' => '16',
            ],
        ];
        $message = $this->getMessage(EventType::CustomerUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' has a new default payment method: '),
            new AttributedObject('card', 'Visa *1234', -3),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerUpdatedChangedPaymentSource(): void
    {
        $previous = [
            'payment_source' => [
                'object' => 'card',
            ],
        ];
        $object = [
            'payment_source' => [
                'id' => -3,
                'object' => 'bank_account',
                'last4' => '1234',
                'bank_name' => 'Frost Bank',
            ],
        ];
        $message = $this->getMessage(EventType::CustomerUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' has a new default payment method: '),
            new AttributedObject('bank_account', 'Frost Bank *1234', -3),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerUpdatedDeletedPaymentSource(): void
    {
        $previous = [
            'payment_source' => [
                'object' => 'bank_account',
                'last4' => '1234',
                'bank_name' => 'Frost Bank',
            ],
        ];
        $object = [
            'payment_source' => null,
        ];
        $message = $this->getMessage(EventType::CustomerUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' no longer has a default payment method'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerUpdatedEnrolledAutoPay(): void
    {
        $previous = [
            'autopay' => false,
        ];
        $object = [];
        $message = $this->getMessage(EventType::CustomerUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' enrolled in AutoPay'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerUpdatedDisabledAutoPay(): void
    {
        $previous = [
            'autopay' => true,
        ];
        $object = [];
        $message = $this->getMessage(EventType::CustomerUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' disabled AutoPay'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerUpdatedNotes(): void
    {
        $previous = [
            'notes' => 'blah',
        ];
        $object = [];
        $message = $this->getMessage(EventType::CustomerUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' notes were changed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerDeleted(): void
    {
        $object = ['name' => 'Bob'];
        $message = $this->getMessage(EventType::CustomerDeleted->value, $object);

        $expected = [
            new AttributedObject('customer', 'Bob', null),
            new AttributedString(' was removed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCustomerMerged(): void
    {
        $object = ['original_customer' => ['name' => 'Bob']];
        $message = $this->getMessage(EventType::CustomerMerged->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('customer', 'Bob', -1),
            new AttributedString(' was merged into '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::CustomerCreated->value);
        $message->setCustomer(self::$customer);

        $this->assertEquals('<b>Sherlock</b> was added as a new customer', (string) $message);
    }
}
