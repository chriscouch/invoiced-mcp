<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\VendorMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class VendorMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = VendorMessage::class;

    public function testVendorCreated(): void
    {
        $message = $this->getMessage(EventType::VendorCreated->value);
        $message->setVendor(self::$vendor);

        $expected = [
            new AttributedObject('vendor', 'Test Vendor', -14),
            new AttributedString(' was added as a new vendor'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testVendorUpdated(): void
    {
        $message = $this->getMessage(EventType::VendorUpdated->value);
        $message->setVendor(self::$vendor);

        $expected = [
            new AttributedString('The profile for '),
            new AttributedObject('vendor', 'Test Vendor', -14),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testVendorDeleted(): void
    {
        $object = ['name' => 'Bob'];
        $message = $this->getMessage(EventType::VendorDeleted->value, $object);

        $expected = [
            new AttributedObject('vendor', 'Bob', null),
            new AttributedString(' was removed'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::VendorCreated->value);
        $message->setVendor(self::$vendor);

        $this->assertEquals('<b>Test Vendor</b> was added as a new vendor', (string) $message);
    }
}
