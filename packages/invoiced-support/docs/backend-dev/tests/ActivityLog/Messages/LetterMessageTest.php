<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\LetterMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class LetterMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = LetterMessage::class;

    public function testLetterSent(): void
    {
        $object = [];
        $message = $this->getMessage(EventType::LetterSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setLetter(self::$letter)
            ->setInvoice(self::$invoice);

        $expected = [
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was mailed a '),
            new AttributedObject('letter', 'Letter', [
                'object' => 'invoice',
                'object_id' => self::$invoice->id(),
            ]),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [];
        $message = $this->getMessage(EventType::LetterSent->value, $object);
        $message->setCustomer(self::$customer)
            ->setLetter(self::$letter)
            ->setInvoice(self::$invoice);

        $this->assertEquals('<b>Sherlock</b> was mailed a <b>Letter</b>', (string) $message);
    }
}
