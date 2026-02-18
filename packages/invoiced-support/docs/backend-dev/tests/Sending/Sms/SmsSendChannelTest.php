<?php

namespace App\Tests\Sending\Sms;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Sending\Models\ScheduledSend;
use App\Sending\Sms\Libs\SmsSendChannel;
use App\Sending\Sms\Libs\TextMessageSender;
use App\Tests\AppTestCase;

class SmsSendChannelTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    private function getChannel(): SmsSendChannel
    {
        return new SmsSendChannel(\Mockery::mock(TextMessageSender::class), self::getService('test.transaction_manager'));
    }

    public function testMissingContactInfo(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;

        // should mark send as failed for missing contact info
        $channel->send($send);
        $this->assertTrue($send->failed);
        $this->assertEquals('Contact information is missing.', $send->failure_detail);
    }

    public function testBuildMessage(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;

        // should use default message
        $this->assertEquals(SmsSendChannel::DEFAULT_TEXT, $channel->buildMessage($send->getParameters()));

        // should use message from ScheduledSend
        $send->parameters = ['message' => 'Test'];
        $this->assertEquals('Test', $channel->buildMessage($send->getParameters()));
    }

    public function testBuildToCustomerContacts(): void
    {
        $contact = new Contact();
        $contact->name = 'John Doe';
        $contact->phone = '1234567890';
        $contact->sms_enabled = true;
        $contact->customer = self::$customer;
        $contact->saveOrFail();

        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;

        $expected = [
            [
                'name' => 'John Doe',
                'phone' => '1234567890',
                'country' => 'US',
            ],
        ];

        // should build 'to' value from customer billing contacts
        /** @var ReceivableDocument $document */
        $document = $send->getDocument();
        $this->assertEquals($expected, $channel->buildTo($send->getParameters(), $document));
    }

    public function testBuildToScheduledSend(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->channel = ScheduledSend::SMS_CHANNEL;
        $send->parameters = [
            'to' => [
                [
                    'name' => 'Jane Doe',
                    'phone' => '0987654321',
                    'country' => 'US',
                ],
            ],
        ];
        $send->saveOrFail();

        $expected = [
            [
                'name' => 'Jane Doe',
                'phone' => '0987654321',
                'country' => 'US',
            ],
        ];

        // should build 'to' value from ScheduledSend 'to' value
        /** @var ReceivableDocument $document */
        $document = $send->getDocument();
        $this->assertEquals($expected, $channel->buildTo($send->getParameters(), $document));
    }
}
