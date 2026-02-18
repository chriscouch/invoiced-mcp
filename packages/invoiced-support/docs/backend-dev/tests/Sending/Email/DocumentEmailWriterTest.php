<?php

namespace App\Tests\Sending\Email;

use App\AccountsReceivable\Models\Invoice;
use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Interfaces\SendableDocumentInterface;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\InboxEmail;
use App\Sending\Email\ValueObjects\DocumentEmail;
use App\Tests\AppTestCase;

class DocumentEmailWriterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasInbox();
        self::hasCustomer();
    }

    public function setUp(): void
    {
        parent::setUp();
        self::hasInvoice();
    }

    private function buildMessage(SendableDocumentInterface $document): DocumentEmail
    {
        $writer = AppTestCase::getService('test.document_email_writer');
        $writer->setLogger(self::$logger);

        $emailTemplate = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $to = [['email' => 'test@example.com', 'name' => 'TEST']];

        $message = $this->getFactory()->make($document, $emailTemplate, $to, [], null, 'subj', 'body');
        $writer->write($message);

        return $message;
    }

    public function testWrite(): void
    {
        $message = $this->buildMessage(self::$invoice);

        // validate inbox model
        /** @var EmailThread $emailThread */
        $emailThread = $message->getEmailThread();
        $emails = InboxEmail::where('thread_id', $emailThread->id())->all();
        $this->assertCount(1, $emails);
        $this->assertEquals('subj', $emails[0]->subject);
        $messageId = $message->getHeader('Message-ID');
        $this->assertEquals($messageId, $emails[0]->message_id);

        $this->assertEquals('test@example.com', $emails[0]->from['email_address']);
        $this->assertEquals('TEST', $emails[0]->from['name']);

        $this->assertEquals('test@example.com', $emails[0]->to[0]['email_address']);
        $this->assertEquals('TEST', $emails[0]->to[0]['name']);
        $expected = [];
        $this->assertEquals($expected, $emails[0]->cc);
    }

    public function testInboxDocumentSend(): void
    {
        $message = $this->buildMessage(self::$invoice);

        /** @var Invoice $document */
        $document = $message->getDocument();
        $document->refresh();
        $this->assertTrue($document->sent);

        $this->assertInstanceOf(InboxEmail::class, $message->getSentEmail());
    }

    public function getFactory(): DocumentEmailFactory
    {
        return new DocumentEmailFactory('test.invoicedmail.com', self::getService('translator'));
    }
}
