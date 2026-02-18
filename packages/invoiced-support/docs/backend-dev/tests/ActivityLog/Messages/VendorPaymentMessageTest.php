<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\VendorPaymentMessage;
use App\ActivityLog\ValueObjects\AttributedMoneyAmount;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class VendorPaymentMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = VendorPaymentMessage::class;

    public function testVendorPaymentCreated(): void
    {
        $obj = [
            'amount' => 100,
            'currency' => 'usd',
        ];
        $associations = [
            'vendor_payment' => -15,
        ];
        $message = $this->getMessage(EventType::VendorPaymentCreated->value, $obj, $associations);
        $message->setVendor(self::$vendor);

        $expected = [
            new AttributedString('Paid '),
            new AttributedObject('vendor_payment', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -15),
            new AttributedString(' to '),
            new AttributedObject('vendor', 'Test Vendor', -14),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testVendorPaymentUpdated(): void
    {
        $message = $this->getMessage(EventType::VendorPaymentUpdated->value);
        $message->setVendor(self::$vendor);

        $expected = [
            new AttributedString('Payment to '),
            new AttributedObject('vendor', 'Test Vendor', -14),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testVendorPaymentDeleted(): void
    {
        $object = ['vendor' => ['name' => 'Test Vendor']];
        $associations = ['vendor' => -14];
        $message = $this->getMessage(EventType::VendorPaymentDeleted->value, $object, $associations);

        $expected = [
            new AttributedString('Payment to '),
            new AttributedObject('vendor', 'Test Vendor', -14),
            new AttributedString(' was voided'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $obj = [
            'amount' => 100,
            'currency' => 'usd',
        ];
        $message = $this->getMessage(EventType::VendorPaymentCreated->value, $obj);
        $message->setVendor(self::$vendor);

        $this->assertEquals('Paid <b>$100.00</b> to <b>Test Vendor</b>', (string) $message);
    }
}
