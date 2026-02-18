<?php

namespace App\Tests\Sending\Sms;

use App\Core\I18n\Countries;
use App\Sending\Sms\Libs\TextMessageSender;
use App\Sending\Sms\Transport\TwilioTransport;
use App\Tests\AppTestCase;
use Symfony\Component\Lock\LockFactory;

class TextMessageSenderTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::$company->features->enable('sms');
    }

    /**
     * Tests that messages sent to contacts are unique and correctly parse
     * the contact_name variable from SMS templates.
     */
    public function testUniqueMessagePerContact(): void
    {
        $lockFactory = \Mockery::mock(LockFactory::class);
        $lockFactory->shouldReceive('createLock')
            ->andReturn(new NullLock());

        $transport = \Mockery::mock(TwilioTransport::class);
        $transport->shouldReceive('send')
            ->withArgs([self::$customer->tenant(), '+12025550108', 'Hello, Test Customer 1']) // Main assertion for this test. Asserts that the contact name is correct.
            ->andReturn([
                'to' => '2025550108',
                'message' => 'Hello, Test Customer 1',
                'state' => 'sent',
            ])->once();
        $transport->shouldReceive('send')
            ->withArgs([self::$customer->tenant(), '+12025550116', 'Hello, Test Customer 2']) // Main assertion for this test. Asserts that the contact name is correct.
            ->andReturn([
                'to' => '2025550116',
                'message' => 'Hello, Test Customer 2',
                'state' => 'sent',
            ])->once();

        $sender = \Mockery::mock(TextMessageSender::class, [new Countries(), self::getService('test.event_spool'), $lockFactory, $transport, self::getService('translator')])->makePartial();
        $sender->shouldReceive('acquireLock')->andReturn(new NullLock());

        $to = [
            [
                'name' => 'Test Customer 1',
                'phone' => '2025550108',
            ],
            [
                'name' => 'Test Customer 2',
                'phone' => '2025550116',
            ],
        ];

        $template = 'Hello, {{contact_name}}';
        $sender->send(self::$customer, self::$invoice, $to, $template, [], null, strtotime('2021-04-01 8:00:00'));
    }
}
