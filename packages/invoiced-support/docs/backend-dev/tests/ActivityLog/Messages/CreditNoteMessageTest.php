<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\CreditNoteMessage;
use App\ActivityLog\ValueObjects\AttributedMoneyAmount;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\AccountsReceivable\ValueObjects\CreditNoteStatus;

class CreditNoteMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = CreditNoteMessage::class;

    public function testCreditNoteCreated(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::CreditNoteCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedString('A credit note for '),
            new AttributedObject('credit_note', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -10),
            new AttributedString(' was issued for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteDraft(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'draft' => true,
        ];
        $message = $this->getMessage(EventType::CreditNoteCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedString('A credit note for '),
            new AttributedObject('credit_note', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -10),
            new AttributedString(' was drafted for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteUpdated(): void
    {
        $message = $this->getMessage(EventType::CreditNoteUpdated->value);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteUpdatedIssued(): void
    {
        $previous = ['draft' => true];
        $object = ['draft' => false];
        $message = $this->getMessage(EventType::CreditNoteUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was issued'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteUpdatedSent(): void
    {
        $previous = ['sent' => false];
        $object = ['sent' => true];
        $message = $this->getMessage(EventType::CreditNoteUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was marked sent'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteUpdatedChangedStatus(): void
    {
        $previous = ['status' => CreditNoteStatus::OPEN];
        $object = ['status' => CreditNoteStatus::CLOSED];
        $message = $this->getMessage(EventType::CreditNoteUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' went from "Open" to "Closed"'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteUpdatedChangedTotal(): void
    {
        $previous = ['total' => 100];
        $object = ['total' => 200, 'currency' => 'usd'];
        $message = $this->getMessage(EventType::CreditNoteUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had its total changed from $100.00 to $200.00'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteUpdatedChangedBalance(): void
    {
        $previous = ['balance' => 100];
        $object = ['balance' => 50, 'currency' => 'usd'];
        $message = $this->getMessage(EventType::CreditNoteUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had its balance changed from $100.00 to $50.00'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteUpdatedClosed(): void
    {
        $previous = ['closed' => false];
        $object = ['closed' => true];
        $message = $this->getMessage(EventType::CreditNoteUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was closed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteUpdatedReopened(): void
    {
        $previous = ['closed' => true];
        $object = ['closed' => false];
        $message = $this->getMessage(EventType::CreditNoteUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was reopened'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteDeleted(): void
    {
        $object = [
            'name' => 'Credit Note',
            'number' => 'CRE-0001',
        ];
        $message = $this->getMessage(EventType::CreditNoteDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('credit_note', 'Credit Note CRE-0001', null),
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
        $message = $this->getMessage(EventType::CreditNoteCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $this->assertEquals('A credit note for <b>$100.00</b> was issued for <b>Sherlock</b>', (string) $message);
    }
}
