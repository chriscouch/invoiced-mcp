<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\Messages\LineItemMessage;
use App\ActivityLog\ValueObjects\AttributedMoneyAmount;
use App\ActivityLog\ValueObjects\AttributedObject;
use App\ActivityLog\ValueObjects\AttributedString;

class LineItemMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = LineItemMessage::class;

    public function testLineItemCreated(): void
    {
        $message = $this->getMessage(EventType::LineItemCreated->value, ['amount' => 100], ['line_item' => -5]);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedString('New '),
            new AttributedObject('line_item', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' pending line item for '),
            new AttributedObject('customer', 'Sherlock', -2),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testLineItemUpdated(): void
    {
        $message = $this->getMessage(EventType::LineItemUpdated->value, ['amount' => 100], ['line_item' => -5]);
        $message->setCustomer(self::$customer);

        $expected = [
            new AttributedObject('line_item', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' pending line item for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was updated'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testLineItemDeleted(): void
    {
        $message = $this->getMessage(EventType::LineItemDeleted->value, ['amount' => 100], ['line_item' => -5]);
        $message->setCustomer(self::$customer);
        $expected = [
            new AttributedObject('line_item', new AttributedMoneyAmount('usd', 100, self::$moneyFormat), -5),
            new AttributedString(' pending line item for '),
            new AttributedObject('customer', 'Sherlock', -2),
            new AttributedString(' was deleted'),
        ];

        $this->assertEquals($expected, $message->generate());
    }

    public function testToString(): void
    {
        $message = $this->getMessage(EventType::LineItemCreated->value, ['amount' => 100], ['line_item' => -5]);
        $message->setCustomer(self::$customer);

        $this->assertEquals('New <b>$100.00</b> pending line item for <b>Sherlock</b>', (string) $message);
    }
}
