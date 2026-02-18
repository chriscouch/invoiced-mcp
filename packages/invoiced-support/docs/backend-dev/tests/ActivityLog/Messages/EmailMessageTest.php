<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;
use App\Sending\Email\Models\EmailTemplate;
use App\ActivityLog\Libs\Messages\EmailMessage;

class EmailMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = EmailMessage::class;

    public function testEmailSent(): void
    {
        $object = [
            'template' => EmailTemplate::UNPAID_INVOICE,
        ];
        $message = $this->getMessage(EventType::EmailSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setEmail(self::$email)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was emailed an '),
            new AttributedObject('email', 'Invoice Reminder', [
                'object' => 'invoice',
                'object_id' => -3,
            ]),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEmailNotSent(): void
    {
        $object = [
            'template' => EmailTemplate::PAYMENT_RECEIPT,
        ];
        $message = $this->getMessage(EventType::EmailNotSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setEmail(self::$email)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedString('Failed to send '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' (test@example.com) a '),
            new AttributedObject('email', 'Payment Receipt', [
                'object' => 'transaction',
                'object_id' => -5,
            ]),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEmailNotSentNoTemplate(): void
    {
        $object = [];
        $message = $this->getMessage(EventType::EmailSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setEmail(self::$email)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was emailed an '),
            new AttributedObject('email', 'Email', []),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testEmailSentNoTemplate(): void
    {
        $object = [];
        $message = $this->getMessage(EventType::EmailNotSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setEmail(self::$email)
            ->setTransaction(self::$transaction);

        $expected = [
            new AttributedString('Failed to send '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' (test@example.com) an '),
            new AttributedObject('email', 'Email', []),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [
            'template' => EmailTemplate::UNPAID_INVOICE,
        ];
        $message = $this->getMessage(EventType::EmailSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setEmail(self::$email)
            ->setInvoice(self::$invoice);

        $this->assertEquals('<b>Sherlock</b> was emailed an <b>Invoice Reminder</b>', (string) $message);
    }
}
