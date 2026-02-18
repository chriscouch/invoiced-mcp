<?php

namespace App\Tests\Sending\Models;

use App\Sending\Models\ScheduledSend;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ScheduledSendTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasCreditNote();
    }

    public function testCreate(): void
    {
        $now = new CarbonImmutable();

        $parameters = [
            'to' => [
                [
                    'name' => 'John Doe',
                    'email' => 'john@doe.com',
                ],
            ],
            'subject' => 'Subject',
            'message' => 'New Invoice',
        ];

        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->parameters = $parameters;
        $send->setSendAfter($now);
        $send->saveOrFail();

        $send->refresh();
        $this->assertEquals((int) self::$invoice->id(), $send->invoice_id);
        $this->assertEquals(ScheduledSend::EMAIL_CHANNEL, $send->channel);
        $this->assertEquals($parameters, $send->parameters);
        $this->assertEquals($now->toDateTimeString(), $send->send_after);
        $this->assertFalse($send->sent);
        $this->assertFalse($send->canceled);
        $this->assertFalse($send->failed);
        $this->assertNull($send->failure_detail);
    }

    public function testValidateDocument(): void
    {
        // test that a document must be configured
        $send = new ScheduledSend();
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $this->assertFalse($send->save());
        $this->assertEquals('Required document is missing', (string) $send->getErrors());

        // test multiple documents
        $send->invoice = self::$invoice;
        $send->credit_note = self::$creditNote;
        $this->assertFalse($send->save());
        $this->assertEquals('Multiple documents is unsupported', (string) $send->getErrors());

        // test invalid channel,document combination
        $send->invoice = null;
        $send->channel = ScheduledSend::SMS_CHANNEL;
        $this->assertFalse($send->save());
        $this->assertEquals("The channel 'sms' does not support the document type: credit_note", (string) $send->getErrors());

        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $this->assertTrue($send->save());
    }

    public function testUpdate(): void
    {
        $now = new CarbonImmutable();

        // create
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->setSendAfter($now);
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->saveOrFail();

        // update
        $send->setSendAfter($now->addDay());
        $send->channel = ScheduledSend::SMS_CHANNEL;
        $send->sent = true;
        $send->canceled = true;
        $send->failed = true;
        $send->saveOrFail();

        $send->refresh();
        $this->assertEquals($now->addDay()->toDateTimeString(), $send->send_after);
        $this->assertEquals(ScheduledSend::SMS_CHANNEL, $send->channel);
        $this->assertTrue($send->sent);
        $this->assertTrue($send->canceled);
        $this->assertTrue($send->failed);
    }

    public function testDelete(): void
    {
        $now = new CarbonImmutable();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->setSendAfter($now);
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->saveOrFail();

        $this->assertTrue($send->delete());
    }

    public function testQuery(): void
    {
        $now = new CarbonImmutable();
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->setSendAfter($now);
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->saveOrFail();

        $found = ScheduledSend::where('invoice_id', self::$invoice->id())
            ->oneOrNull();
        $this->assertInstanceOf(ScheduledSend::class, $found);
    }

    public function testMarkSent(): void
    {
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->markSent();

        $send->refresh();
        $this->assertNotNull($send->id());
        $this->assertTrue($send->sent);
        $this->assertNotNull($send->sent_at);
    }

    public function testMarkCanceled(): void
    {
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->canceled = true;
        $send->saveOrFail();

        $this->assertTrue($send->canceled);
    }

    public function testMarkFailed(): void
    {
        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->markFailed('Send failure');
        $send->saveOrFail();

        $this->assertTrue($send->failed);
        $this->assertEquals('Send failure', $send->failure_detail);
    }

    public function testToArray(): void
    {
        $parameters = [
            'to' => [
                [
                    'name' => 'John Doe',
                    'email' => 'john@doe.com',
                ],
            ],
            'subject' => 'Subject',
            'message' => 'Test Message',
        ];
        $now = new CarbonImmutable();

        $send = new ScheduledSend();
        $send->invoice = self::$invoice;
        $send->setSendAfter($now);
        $send->channel = ScheduledSend::EMAIL_CHANNEL;
        $send->parameters = $parameters;
        $send->saveOrFail();
        $send->refresh();

        $expected = [
            'id' => $send->id(),
            'invoice_id' => self::$invoice->id(),
            'credit_note_id' => null,
            'estimate_id' => null,
            'channel' => ScheduledSend::EMAIL_CHANNEL,
            'parameters' => [
                'to' => [
                    [
                        'name' => 'John Doe',
                        'email' => 'john@doe.com',
                    ],
                ],
                'subject' => 'Subject',
                'message' => 'Test Message',
            ],
            'send_after' => $now->toDateTimeString(),
            'sent' => false,
            'canceled' => false,
            'skipped' => false,
            'failed' => false,
            'failure_detail' => null,
            'ignore_failure' => false,
            'sent_at' => null,
            'reference' => null,
            'replacement_id' => null,
        ];

        $result = $send->toArray();
        unset($result['created_at']);
        unset($result['updated_at']);
        $this->assertEquals($expected, $result);
    }
}
