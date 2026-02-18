<?php

namespace App\Tests\Sending\Email;

use App\Companies\Models\Company;
use App\Core\Authentication\Models\User;
use App\Core\Files\Models\File;
use App\Sending\Email\ValueObjects\Email;
use App\Sending\Email\ValueObjects\EmailAttachment;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Tests\AppTestCase;

class CustomerEmailTest extends AppTestCase
{
    private function getEmail(): Email
    {
        return new Email();
    }

    public function testCompany(): void
    {
        $email = $this->getEmail();
        $company = new Company();
        $email->company($company);
        $this->assertEquals($company, $email->getCompany());
    }

    public function testFrom(): void
    {
        $email = $this->getEmail();
        $from = new NamedAddress('Test', 'test@example.com');
        $email->from($from);
        $this->assertEquals($from, $email->getFrom());
    }

    public function testTo(): void
    {
        $email = $this->getEmail();
        $this->assertEquals([], $email->getTo());
        $to = [new NamedAddress('test@example.com', 'Test')];
        $email->to($to);
        $this->assertEquals($to, $email->getTo());
        $to = [new NamedAddress('test@example.com', 'Test'), new NamedAddress('test2@example.com')];
        $email->to($to);
        $this->assertEquals('test@example.com,test2@example.com', $email->getToEmails());
    }

    public function testCc(): void
    {
        $email = $this->getEmail();
        $this->assertEquals([], $email->getCc());
        $cc = [new NamedAddress('Test', 'test@example.com')];
        $email->cc($cc);
        $this->assertEquals($cc, $email->getCc());
    }

    public function testBcc(): void
    {
        $email = $this->getEmail();
        $this->assertEquals([], $email->getBcc());
        $bcc = [new NamedAddress('Test', 'test@example.com')];
        $email->bcc($bcc);
        $this->assertEquals($bcc, $email->getBcc());
    }

    public function testSubject(): void
    {
        $email = $this->getEmail();
        $this->assertEquals('', $email->getSubject());
        $email->subject('Test');
        $this->assertEquals('Test', $email->getSubject());
    }

    public function testPlainText(): void
    {
        $email = $this->getEmail();
        $this->assertNull($email->getPlainText());

        $email->plainText('test');
        $this->assertEquals('test', $email->getPlainText());
    }

    public function testHtml(): void
    {
        $email = $this->getEmail();
        $this->assertNull($email->getHtml());
        $this->assertNull($email->getHtml(true));

        $email->html('test');
        $this->assertEquals('test', $email->getHtml());
        $this->assertEquals('test', $email->getHtml(true));

        $email->html('track', true);
        $this->assertEquals('test', $email->getHtml());
        $this->assertEquals('track', $email->getHtml(true));
    }

    public function testAttachments(): void
    {
        $email = $this->getEmail();
        $this->assertEquals([], $email->getAttachments());
        $attachments = [new EmailAttachment('filename', 'tpe', 'content')];
        $email->attachments($attachments);
        $this->assertEquals($attachments, $email->getAttachments());
    }

    public function testHeaders(): void
    {
        $email = $this->getEmail();
        $this->assertEquals([], $email->getHeaders());
        $this->assertNull($email->getHeader('Message-ID'));
        $headers = ['Message-ID' => 'test'];
        $email->headers($headers);
        $this->assertEquals($headers, $email->getHeaders());
        $this->assertEquals('test', $email->getHeader('Message-ID'));
    }

    public function testSentBy(): void
    {
        $email = $this->getEmail();
        $this->assertNull($email->getSentBy());
        $user = new User();
        $email->sentBy($user);
        $this->assertEquals($user, $email->getSentBy());
    }

    public function testFiles(): void
    {
        $email = $this->getEmail();
        $this->assertEquals([], $email->getFiles());
        $files = [new File()];
        $email->setFiles($files);
        $this->assertEquals($files, $email->getFiles());
    }
}
