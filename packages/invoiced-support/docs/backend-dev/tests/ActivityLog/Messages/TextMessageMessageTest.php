<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\TextMessageMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class TextMessageMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = TextMessageMessage::class;

    public function testTextMessageSent(): void
    {
        $object = [];
        $message = $this->getMessage(EventType::TextMessageSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setTextMessage(self::$textMessage)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was sent a '),
            new AttributedObject('text_message', 'Text Message', [
                'object' => 'invoice',
                'object_id' => self::$invoice->id(),
            ]),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [];
        $message = $this->getMessage(EventType::TextMessageSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setTextMessage(self::$textMessage)
            ->setInvoice(self::$invoice);

        $this->assertEquals('<b>Sherlock</b> was sent a <b>Text Message</b>', (string) $message);
    }
}
