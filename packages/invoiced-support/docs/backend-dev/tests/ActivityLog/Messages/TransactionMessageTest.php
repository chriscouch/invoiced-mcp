<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\TransactionMessage;
use App\ActivityLog\ValueObjects\AttributedMoneyAmount;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\PaymentProcessing\Models\PaymentMethod;
use App\CashApplication\Models\Transaction;

class TransactionMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = TransactionMessage::class;

    public function testPaymentCreated(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'method' => PaymentMethod::CREDIT_CARD,
        ];
        $message = $this->getMessage(EventType::TransactionCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' paid '),
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' via credit card'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testRefundCreated(): void
    {
        $object = [
            'type' => 'refund',
            'currency' => 'usd',
            'amount' => 100,
            'method' => PaymentMethod::CASH,
        ];
        $message = $this->getMessage(EventType::TransactionCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was refunded '),
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' with cash'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testAdjustmentCreated(): void
    {
        $object = [
            'type' => 'adjustment',
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::TransactionCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' adjustment for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was created'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditCreated(): void
    {
        $object = [
            'type' => 'adjustment',
            'currency' => 'usd',
            'amount' => -100,
        ];
        $message = $this->getMessage(EventType::TransactionCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' credit for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was created'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPendingPaymentCreated(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'status' => Transaction::STATUS_PENDING,
            'method' => PaymentMethod::ACH,
        ];
        $message = $this->getMessage(EventType::TransactionCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedString('Initiated '),
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' ACH payment from '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testFailedPaymentCreated(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'status' => Transaction::STATUS_FAILED,
            'method' => PaymentMethod::CREDIT_CARD,
        ];
        $message = $this->getMessage(EventType::TransactionCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' credit card payment from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' failed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentUpdated(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'method' => PaymentMethod::WIRE_TRANSFER,
        ];
        $message = $this->getMessage(EventType::TransactionUpdated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' wire transfer payment from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentMarkedSent(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $previous = [
            'sent' => false,
        ];
        $message = $this->getMessage(EventType::TransactionUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' payment from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was marked sent'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testRefundUpdated(): void
    {
        $object = [
            'type' => 'refund',
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::TransactionUpdated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' refund from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testAdjustmentUpdated(): void
    {
        $object = [
            'type' => 'adjustment',
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::TransactionUpdated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' adjustment for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditUpdated(): void
    {
        $object = [
            'type' => 'adjustment',
            'currency' => 'usd',
            'amount' => -100,
        ];
        $previous = [
            'amount' => -50,
        ];
        $message = $this->getMessage(EventType::TransactionUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' credit for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentUpdatedChangedStatus(): void
    {
        $previous = ['status' => Transaction::STATUS_PENDING];
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'status' => Transaction::STATUS_FAILED,
            'method' => PaymentMethod::ACH,
        ];
        $message = $this->getMessage(EventType::TransactionUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' ACH payment from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' went from "Pending" to "Failed"'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testPaymentDeleted(): void
    {
        $object = [
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::TransactionDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('A payment for '),
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), null),
            new AttributedString(' from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was removed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testRefundDeleted(): void
    {
        $object = [
            'type' => 'refund',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::TransactionDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('A refund for '),
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), null),
            new AttributedString(' from '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was removed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testAdjustmentDeleted(): void
    {
        $object = [
            'type' => 'adjustment',
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::TransactionDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), null),
            new AttributedString(' adjustment for '),

            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was removed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditDeleted(): void
    {
        $object = [
            'type' => 'adjustment',
            'currency' => 'usd',
            'amount' => -100,
        ];
        $message = $this->getMessage(EventType::TransactionDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('transaction', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), null),
            new AttributedString(' credit for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was removed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'method' => PaymentMethod::CHECK,
        ];
        $message = $this->getMessage(EventType::TransactionCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setTransaction(self::$transaction);

        $this->assertEquals('<b>Sherlock</b> paid <b>$100.00</b> via check', (string) $message);
    }
}
