<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\NetworkDocumentMessage;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class NetworkDocumentMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = NetworkDocumentMessage::class;

    public function testNetworkDocumentSent(): void
    {
        $object = [
            'type' => 'Invoice',
            'reference' => 'INV-00001',
            'to_company' => ['name' => 'Acme Corp'],
        ];
        $message = $this->getMessage(EventType::NetworkDocumentSent->value, $object);
        $message->setNetworkDocument(self::$networkDocument);

        $expected = [
            new AttributedObject('network_document', 'Invoice INV-00001', -15),
            new AttributedString(' was sent to Acme Corp'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testNetworkDocumentReceived(): void
    {
        $object = [
            'type' => 'Invoice',
            'reference' => 'INV-00001',
            'from_company' => ['name' => 'Acme Corp'],
        ];
        $message = $this->getMessage(EventType::NetworkDocumentReceived->value, $object);
        $message->setNetworkDocument(self::$networkDocument);

        $expected = [
            new AttributedObject('network_document', 'Invoice INV-00001', -15),
            new AttributedString(' was received from Acme Corp'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testNetworkDocumentStatusUpdated(): void
    {
        $object = [
            'type' => 'Invoice',
            'reference' => 'INV-00001',
            'current_status' => 'Approved',
        ];
        $message = $this->getMessage(EventType::NetworkDocumentStatusUpdated->value, $object);
        $message->setNetworkDocument(self::$networkDocument);

        $expected = [
            new AttributedObject('network_document', 'Invoice INV-00001', -15),
            new AttributedString(' status was updated: Approved'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $object = [
            'type' => 'Invoice',
            'reference' => 'INV-00001',
            'to_company' => ['name' => 'Acme Corp'],
        ];
        $message = $this->getMessage(EventType::NetworkDocumentSent->value, $object);
        $message->setNetworkDocument(self::$networkDocument);

        $this->assertEquals('<b>Invoice INV-00001</b> was sent to Acme Corp', (string) $message);
    }
}
