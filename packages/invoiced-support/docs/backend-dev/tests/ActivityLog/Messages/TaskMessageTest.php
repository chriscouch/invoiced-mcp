<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\TaskMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class TaskMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = TaskMessage::class;

    public function testNoteCreated(): void
    {
        $object = ['name' => 'Terminate service'];
        $message = $this->getMessage(EventType::TaskCreated->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Task for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was created: Terminate service'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testTaskUpdated(): void
    {
        $object = ['name' => 'Terminate service'];
        $message = $this->getMessage(EventType::TaskUpdated->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Task for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated: Terminate service'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testTaskDeleted(): void
    {
        $object = ['name' => 'Terminate service'];
        $message = $this->getMessage(EventType::TaskDeleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Task for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was deleted: Terminate service'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testTaskCompleted(): void
    {
        $object = ['name' => 'Terminate service'];
        $message = $this->getMessage(EventType::TaskCompleted->value, $object);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('Task completed for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(': Terminate service'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = ['name' => 'Terminate service'];
        $message = $this->getMessage(EventType::TaskCompleted->value, $object);
        $message->setCustomer(self::$customer);

        $this->assertEquals('Task completed for <b>Sherlock</b>: Terminate service', (string) $message);
    }
}
