<?php

namespace App\Tests\ActivityLog\Messages;

use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\EstimateMessage;
use App\ActivityLog\ValueObjects\AttributedMoneyAmount;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class EstimateMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = EstimateMessage::class;

    public function testEstimateCreated(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::EstimateCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedString('An estimate for '),
            new AttributedObject('estimate', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -4),
            new AttributedString(' was issued for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateCreatedDraft(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'draft' => true,
        ];
        $message = $this->getMessage(EventType::EstimateCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedString('An estimate for '),
            new AttributedObject('estimate', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -4),
            new AttributedString(' was drafted for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateUpdated(): void
    {
        $message = $this->getMessage(EventType::EstimateUpdated->value);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateUpdatedIssued(): void
    {
        $previous = ['draft' => true];
        $object = ['draft' => false];
        $message = $this->getMessage(EventType::EstimateUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was issued'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateUpdatedSent(): void
    {
        $previous = ['sent' => false];
        $message = $this->getMessage(EventType::EstimateUpdated->value, [], [], $previous);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was marked sent'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateUpdatedChangedStatus(): void
    {
        $previous = ['status' => EstimateStatus::SENT];
        $object = ['status' => EstimateStatus::APPROVED];
        $message = $this->getMessage(EventType::EstimateUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' went from "Sent" to "Approved"'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateUpdatedChangedTotal(): void
    {
        $previous = ['total' => 100];
        $object = ['total' => 200, 'currency' => 'usd'];
        $message = $this->getMessage(EventType::EstimateUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had its total changed from $100.00 to $200.00'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateUpdatedClosed(): void
    {
        $previous = ['closed' => false];
        $object = ['closed' => true];
        $message = $this->getMessage(EventType::EstimateUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was closed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateUpdatedReopened(): void
    {
        $previous = ['closed' => true];
        $object = ['closed' => false];
        $message = $this->getMessage(EventType::EstimateUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was reopened'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateApproved(): void
    {
        $object = [
            'initials' => 'JTK',
        ];
        $message = $this->getMessage(EventType::EstimateApproved->value, $object);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was approved by JTK'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateDeleted(): void
    {
        $object = [
            'name' => 'ESTIMATE',
            'number' => 'EST-001',
            'customerName' => 'Customer',
        ];
        $message = $this->getMessage(EventType::EstimateDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('estimate', 'ESTIMATE EST-001', null),
            new AttributedString(' for '),
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
        ];
        $message = $this->getMessage(EventType::EstimateCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $this->assertEquals('An estimate for <b>$100.00</b> was issued for <b>Sherlock</b>', (string) $message);
    }
}
