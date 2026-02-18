<?php

namespace App\Tests\Sending\Sms\Libs;

use App\Core\I18n\Countries;
use App\ActivityLog\Libs\EventSpool;
use App\Sending\Sms\Libs\TextMessageSender;
use App\Sending\Sms\Transport\TwilioTransport;
use App\Tests\AppTestCase;
use Mockery;
use Symfony\Component\Lock\LockFactory;

class TextMessageSenderTest extends AppTestCase
{
    public function testGetPhoneNumber(): void
    {
        $sender = new TextMessageSender(
            new Countries(),
            Mockery::mock(EventSpool::class),
            Mockery::mock(LockFactory::class),
            Mockery::mock(TwilioTransport::class),
            self::getService('app.translator')
        );
        $this->assertNull($sender->getPhoneNumber(null, 'US'));
        $this->assertEquals('+15121234567', $sender->getPhoneNumber('+15121234567', 'US'));
        $this->assertEquals('+15121234567', $sender->getPhoneNumber('5121234567', 'US'));
        $this->assertEquals('+18491234567', $sender->getPhoneNumber('1234567', 'DO'));
        $this->assertEquals('+18491234567', $sender->getPhoneNumber('+18491234567', 'DO'));
        $this->assertEquals('+18091234567', $sender->getPhoneNumber('+18091234567', 'DO'));
    }
}
