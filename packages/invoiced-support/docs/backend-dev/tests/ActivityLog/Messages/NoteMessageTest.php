<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\NoteMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class NoteMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = NoteMessage::class;

    public function testNoteCreated(): void
    {
        $message = $this->getMessage(EventType::NoteCreated->value);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Note for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was created'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testNoteUpdated(): void
    {
        $message = $this->getMessage(EventType::NoteUpdated->value);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Note for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testNoteDeleted(): void
    {
        $message = $this->getMessage(EventType::NoteDeleted->value);
        $message->setCustomer(self::$customer);
        $expected = [
            new AttributedString('Note for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was deleted'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::NoteCreated->value);
        $message->setCustomer(self::$customer);

        $this->assertEquals('Note for <b>Sherlock</b> was created', (string) $message);
    }
}
