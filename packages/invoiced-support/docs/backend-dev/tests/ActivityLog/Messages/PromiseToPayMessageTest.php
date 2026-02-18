<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\PromiseToPayMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class PromiseToPayMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = PromiseToPayMessage::class;

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

    public function testToString(): void
    {
        $object = [
            'date' => mktime(0, 0, 0, 8, 28, 2015),
            'method' => 'wire_transfer',
        ];
        $message = $this->getMessage(EventType::InvoicePaymentExpected->value, $object);
        $message->setCustomer(self::$customer)
            ->setInvoice(self::$invoice);

        $this->assertEquals('<b>Sherlock</b> expects payment to arrive by Aug 28, 2015 via wire transfer for <b>Invoice INV-0001</b>', (string) $message);
    }
}
