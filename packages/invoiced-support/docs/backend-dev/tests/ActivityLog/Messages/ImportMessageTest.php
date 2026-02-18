<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\ImportMessage;
use App\ActivityLog\ValueObjects\AttributedString;

class ImportMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = ImportMessage::class;

    public function testImportFinished(): void
    {
        $object = [
            'type' => 'customer',
            'num_imported' => 10,
        ];
        $message = $this->getMessage(EventType::ImportFinished->value, $object);

        $expected = [
            new AttributedString('10 customers were imported'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [
            'type' => 'customer',
            'num_imported' => 10,
        ];
        $message = $this->getMessage(EventType::ImportFinished->value, $object);

        $this->assertEquals('10 customers were imported', (string) $message);
    }
}
