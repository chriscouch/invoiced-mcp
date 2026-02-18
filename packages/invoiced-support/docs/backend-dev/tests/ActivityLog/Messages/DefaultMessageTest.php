<?php

namespace App\Tests\ActivityLog\Messages;

use App\ActivityLog\Libs\Messages\DefaultMessage;

class DefaultMessageTest extends MessageTestCaseBase
{
    const MESSAGE_CLASS = DefaultMessage::class;

    public function testToString(): void
    {
        $message = $this->getMessage('event.test');

        $this->assertEquals('event.test', (string) $message);
    }
}
