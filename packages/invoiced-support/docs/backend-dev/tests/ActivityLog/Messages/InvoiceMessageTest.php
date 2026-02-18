<?php

namespace App\Tests\ActivityLog\Messages;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\InvoiceMessage;
use App\ActivityLog\ValueObjects\AttributedMoneyAmount;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class InvoiceMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = InvoiceMessage::class;

    public function testInvoiceCreated(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::InvoiceCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was invoiced for '),
            new AttributedObject('invoice', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -3),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceDraft(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
            'draft' => true,
        ];
        $message = $this->getMessage(EventType::InvoiceCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was drafted an invoice for '),
            new AttributedObject('invoice', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -3),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdated(): void
    {
        $message = $this->getMessage(EventType::InvoiceUpdated->value);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedPaid(): void
    {
        $previous = ['paid' => false];
        $object = ['paid' => true];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, [], [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was marked paid'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedIssued(): void
    {
        $previous = ['draft' => true];
        $object = ['draft' => false];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was issued'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedSent(): void
    {
        $previous = ['sent' => false];
        $object = ['sent' => true];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was marked sent'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedFlagged(): void
    {
        $previous = ['needs_attention' => false];
        $object = ['needs_attention' => true];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was flagged'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedMarkedResolved(): void
    {
        $previous = ['needs_attention' => true];
        $object = ['needs_attention' => false];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was marked as resolved'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedChangedStatus(): void
    {
        $previous = ['status' => InvoiceStatus::NotSent->value];
        $object = ['status' => InvoiceStatus::PastDue->value];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' went from "Not Sent" to "Past Due"'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedChangedTotal(): void
    {
        $previous = ['total' => 100];
        $object = ['total' => 200, 'currency' => 'usd'];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had its total changed from $100.00 to $200.00'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedChangedBalance(): void
    {
        $previous = ['balance' => 100];
        $object = ['balance' => 50, 'currency' => 'usd'];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had its balance changed from $100.00 to $50.00'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedChasingEnabled(): void
    {
        $previous = ['next_chase_on' => 100, 'chase' => false];
        $object = ['chase' => true];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had chasing enabled'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedChasingDisabled(): void
    {
        $previous = ['next_chase_on' => 100, 'chase' => true];
        $object = ['chase' => false];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had chasing disabled'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedChaseUpcoming(): void
    {
        $previous = ['next_chase_on' => null];
        $object = ['next_chase_on' => 100];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was scheduled for chasing on Jan 1, 1970'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedChased(): void
    {
        $previous = ['next_chase_on' => 100];
        $object = [];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' has no further chasing attempts scheduled'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedPaymentAttempt(): void
    {
        $previous = ['attempt_count' => 0];
        $object = ['attempt_count' => 1];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had a payment attempt'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedClosed(): void
    {
        $previous = ['closed' => false];
        $object = ['closed' => true];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was closed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedReopened(): void
    {
        $previous = ['closed' => true];
        $object = ['closed' => false];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was reopened'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedEnrolledAutoPay(): void
    {
        $previous = [
            'autopay' => false,
        ];
        $object = [
            'autopay' => true,
        ];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had AutoPay enabled'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceUpdatedDisabledAutoPay(): void
    {
        $previous = [
            'autopay' => true,
        ];
        $object = [
            'autopay' => false,
        ];
        $message = $this->getMessage(EventType::InvoiceUpdated->value, $object, [], $previous);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' had AutoPay disabled'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoicePaid(): void
    {
        $message = $this->getMessage(EventType::InvoicePaid->value);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was paid in full'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoicePaymentExpected(): void
    {
        $object = [
            'date' => mktime(0, 0, 0, 8, 28, 2015),
            'method' => 'wire_transfer',
        ];
        $message = $this->getMessage(EventType::InvoicePaymentExpected->value, $object);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' expects payment to arrive by Aug 28, 2015 via wire transfer for '),
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceDeleted(): void
    {
        $object = [
            'name' => 'INVOICE',
            'number' => 'INV-001',
        ];
        $message = $this->getMessage(EventType::InvoiceDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('invoice', 'INVOICE INV-001', null),
            new AttributedString(' for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was removed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceFollowUpNoteCreated(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage('invoice.follow_up_note.created', $object);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedString('Follow up note recorded for '),
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::InvoiceCreated->value, $object);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $this->assertEquals('<b>Sherlock</b> was invoiced for <b>$100.00</b>', (string) $message);
    }
}
