<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\CommentMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class CommentMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = CommentMessage::class;

    public function testEstimateCommented(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::EstimateCommented->value, $object);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' commented on '),
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testInvoiceCommented(): void
    {
        $message = $this->getMessage(EventType::InvoiceCommented->value);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' commented on '),
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteCommented(): void
    {
        $message = $this->getMessage(EventType::CreditNoteCommented->value);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' commented on '),
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::EstimateCommented->value, $object);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $this->assertEquals('<b>Sherlock</b> commented on <b>Estimate EST-0001</b>', (string) $message);
    }
}
