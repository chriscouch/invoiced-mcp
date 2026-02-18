<?php

namespace App\Tests\Sending\Email;

use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\AdapterInterface;
use App\Sending\Email\Libs\AdapterFactory;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\ValueObjects\DocumentEmail;
use App\Sending\Email\ValueObjects\EmailAttachment;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Tests\AppTestCase;
use Mockery;

class EmailSenderTest extends AppTestCase
{
    use EmailSenderTrait;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::$company->name = 'Test Company';
        self::$company->email = 'test@example.com';
        self::$company->save();

        self::hasInbox();
        self::hasCustomer();
        self::hasInvoice();
    }

    public function testSendWithoutRecipient(): void
    {
        $this->expectException(SendEmailException::class);
        $this->expectExceptionMessage('No email recipients given. At least one recipient must be provided.');

        $message = $this->getMessage();
        $message->to([]);

        $sender = $this->getSender();
        $sender->send($message);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testSend(): void
    {
        $message = $this->getMessage([['email' => 'test+send@example.com']]);

        $sender = $this->getSender();
        $sender->send($message);
    }

    /**
     * @depends testSend
     */
    public function testSendDebouncing(): void
    {
        $adapter = Mockery::mock(AdapterInterface::class);
        $adapter->shouldReceive('send')->once();
        $adapterFactory = Mockery::mock(AdapterFactory::class);
        $adapterFactory->shouldReceive('get')->andReturn($adapter);

        $message = $this->getMessage([['email' => 'test+debounce@example.com']]);

        // the first send should work
        $sender = $this->getSender($adapterFactory);
        $sender->send($message);

        // the second should throw an exception
        // a separate object is used to simulate a different request / process
        $message2 = $this->getMessage([['email' => 'test+debounce@example.com']]);
        $sender = $this->getSender($adapterFactory);
        $sender->send($message2);
    }

    public function testBuildEmail(): void
    {
        $attachment1 = new EmailAttachment('Test.pdf', 'application/pdf', '__content__');
        $attachment1->setSize(100);
        $attachment2 = new EmailAttachment('Too Large.pdf', 'application/pdf', '__content__');
        $attachment2->setSize(31457280);

        $message = (new DocumentEmail('TEST'))
            ->company(self::$company)
            ->from(new NamedAddress('test@example.com', 'TEST'))
            ->to([new NamedAddress('cust@example.com', 'TO Recipient')])
            ->cc([new NamedAddress('cc@example.com', 'CC Recipient')])
            ->bcc([new NamedAddress('bcc@example.com', 'BCC Recipient')])
            ->subject('Subject')
            ->body('body')
            ->plainText('text')
            ->attachments([$attachment1, $attachment2])
            ->sentBy(self::getService('test.user_context')->get())
            ->headers([
                'Message-ID' => '<test@invoiced.com>',
                'Reply-To' => 'Reply To Name <replyto@example.com>',
            ]);
        $message->html('html');

        $email = EmailSender::buildEmail($message, 41943040);

        $from = $email->getFrom();
        $this->assertCount(1, $from);
        $this->assertEquals('test@example.com', $from[0]->getAddress());
        $this->assertEquals('TEST', $from[0]->getName());

        $to = $email->getTo();
        $this->assertCount(1, $to);
        $this->assertEquals('cust@example.com', $to[0]->getAddress());
        $this->assertEquals('TO Recipient', $to[0]->getName());

        $cc = $email->getCc();
        $this->assertCount(1, $cc);
        $this->assertEquals('cc@example.com', $cc[0]->getAddress());
        $this->assertEquals('CC Recipient', $cc[0]->getName());

        $bcc = $email->getBcc();
        $this->assertCount(1, $bcc);
        $this->assertEquals('bcc@example.com', $bcc[0]->getAddress());
        $this->assertEquals('BCC Recipient', $bcc[0]->getName());

        $replyTo = $email->getReplyTo();
        $this->assertEquals('replyto@example.com', $replyTo[0]->getAddress());
        $this->assertEquals('Reply To Name', $replyTo[0]->getName());

        $this->assertEquals('Subject', $email->getSubject());

        $this->assertEquals('text', $email->getTextBody());
        $this->assertEquals('html', $email->getHtmlBody());

        $headers = $email->getHeaders();
        $this->assertEquals('<test@invoiced.com>', $headers->get('Message-ID')->getBodyAsString()); /* @phpstan-ignore-line */

        $attachments = $email->getAttachments();
        $this->assertCount(2, $attachments);
        $attachment = $attachments[0];
        $this->assertEquals('Test.pdf', $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
        $this->assertEquals('__content__', $attachment->getBody());
        $this->assertEquals('application/pdf', $attachment->getPreparedHeaders()->getHeaderBody('Content-Type'));
        $attachment = $attachments[1];
        $this->assertEquals('Too Large.pdf', $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
        $this->assertEquals('__content__', $attachment->getBody());
        $this->assertEquals('application/pdf', $attachment->getPreparedHeaders()->getHeaderBody('Content-Type'));

        // Test with a 9MB attachment limit
        $email = EmailSender::buildEmail($message, 9437184);
        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $attachment = $attachments[0];
        $this->assertEquals('Test.pdf', $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
        $this->assertEquals('__content__', $attachment->getBody());
        $this->assertEquals('application/pdf', $attachment->getPreparedHeaders()->getHeaderBody('Content-Type'));
    }

    public function testBuildEmailWithQuoteFilename(): void
    {
        $attachment1 = new EmailAttachment('Invoice # INV-FAIL 1234"\'!@#$%^&*()-_=+?,;:{[]}|.pdf', 'application/pdf', '__content__');
        $attachment1->setSize(100);

        $message = (new DocumentEmail('TEST'))
            ->company(self::$company)
            ->from(new NamedAddress('test@example.com', 'TEST'))
            ->to([new NamedAddress('cust@example.com', 'TO Recipient')])
            ->subject('Subject')
            ->body('body')
            ->plainText('text')
            ->attachments([$attachment1])
            ->sentBy(self::getService('test.user_context')->get())
            ->headers([
                'Message-ID' => '<test@invoiced.com>',
                'Reply-To' => 'Reply To Name <replyto@example.com>',
            ])
            ->html('html');

        $email = EmailSender::buildEmail($message, 20971520);

        $attachments = $email->getAttachments();
        $this->assertCount(1, $attachments);
        $attachment = $attachments[0];
        $this->assertEquals('Invoice # INV-FAIL 1234!@#$%^&*()-_=+?,:{[]}|.pdf', $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'));
        $this->assertEquals('__content__', $attachment->getBody());
        $this->assertEquals('application/pdf', $attachment->getPreparedHeaders()->getHeaderBody('Content-Type'));
    }

    private function getMessage(array $to = []): DocumentEmail
    {
        $emailTemplate = EmailTemplate::make(self::$company->id, EmailTemplate::NEW_INVOICE);
        $to = $to ?: self::$invoice->getDefaultEmailContacts();

        return $this->getFactory()->make(self::$invoice, $emailTemplate, $to, [], null, 'test', 'Test');
    }

    public function getFactory(): DocumentEmailFactory
    {
        return new DocumentEmailFactory('test.invoicedmail.com', self::getService('translator'));
    }
}
