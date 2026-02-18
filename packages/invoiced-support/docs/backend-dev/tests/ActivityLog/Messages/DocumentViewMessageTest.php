<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\DocumentViewMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class DocumentViewMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = DocumentViewMessage::class;

    public function testInvoiceViewed(): void
    {
        $message = $this->getMessage(EventType::InvoiceViewed->value);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' viewed '),
            new AttributedObject('invoice', 'Invoice INV-0001', -3),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEstimateViewed(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::EstimateViewed->value, $object);
        $message->setCustomer(self::$customer)
            ->setEstimate(self::$estimate);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' viewed '),
            new AttributedObject('estimate', 'Estimate EST-0001', -4),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testCreditNoteViewed(): void
    {
        $object = [
            'currency' => 'usd',
            'amount' => 100,
        ];
        $message = $this->getMessage(EventType::CreditNoteViewed->value, $object);
        $message->setCustomer(self::$customer)
            ->setCreditNote(self::$creditNote);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' viewed '),
            new AttributedObject('credit_note', 'Credit Note CRE-0001', -10),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::InvoiceViewed->value);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $this->assertEquals('<b>Sherlock</b> viewed <b>Invoice INV-0001</b>', (string) $message);
    }
}
