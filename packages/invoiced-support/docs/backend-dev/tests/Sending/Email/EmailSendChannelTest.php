<?php

namespace App\Tests\Sending\Email;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Libs\EmailSendChannel;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;

class EmailSendChannelTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    private function getChannel(): EmailSendChannel
    {
        return new EmailSendChannel(\Mockery::mock(DocumentEmailFactory::class), \Mockery::mock(EmailSender::class), self::getService('test.transaction_manager'));
    }

    public function testMissingContactInfo(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;

        // remove email from customer to test missing contact info
        $customer = $send->invoice->customer();
        $customerEmail = $customer->email;
        $customer->email = null;

        // should mark send as failed for missing contact info
        $channel->send($send);
        $this->assertTrue($send->failed);
        $this->assertEquals('Contact information is missing.', $send->failure_detail);

        // reset customer email for future tests
        $customer->email = $customerEmail;
    }

    public function testBuildToCustomerContacts(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;

        $expected = [
            [
                'name' => 'Sherlock',
                'email' => 'sherlock@example.com',
            ],
        ];

        // should build 'to' value from customer contacts
        /** @var ReceivableDocument $document */
        $document = $send->getDocument();
        $this->assertEquals($expected, $channel->buildTo($send->getParameters(), $document, null));
    }

    public function testBuildToScheduledSend(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->parameters = [
            'to' => [
                [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ],
        ];
        $send->saveOrFail();

        $expected = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ];

        // should build 'to' value from ScheduledSend 'to' value
        /** @var ReceivableDocument $document */
        $document = $send->getDocument();
        $this->assertEquals($expected, $channel->buildTo($send->getParameters(), $document, null));
    }

    public function testBuildBcc(): void
    {
        $channel = $this->getChannel();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->parameters = [
            'bcc' => [
                [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
                [
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                ],
            ],
        ];
        $send->saveOrFail();

        $expected = 'john@example.com,jane@example.com';
        $this->assertEquals($expected, $channel->buildBcc($send->getParameters()));
    }
}
