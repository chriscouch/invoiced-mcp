<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\ContactMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class ContactMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = ContactMessage::class;

    public function testContactCreated(): void
    {
        $message = $this->getMessage(EventType::ContactCreated->value, ['name' => 'Test Person']);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Contact for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was created: Test Person'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testContactUpdated(): void
    {
        $message = $this->getMessage(EventType::ContactUpdated->value, ['name' => 'Test Person']);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Contact for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated: Test Person'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testContactDeleted(): void
    {
        $message = $this->getMessage(EventType::ContactDeleted->value, ['name' => 'Test Person']);
        $message->setCustomer(self::$customer);
        $expected = [
            new AttributedString('Contact for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was deleted: Test Person'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::ContactCreated->value, ['name' => 'Test Person']);
        $message->setCustomer(self::$customer);

        $this->assertEquals('Contact for <b>Sherlock</b> was created: Test Person', (string) $message);
    }
}
