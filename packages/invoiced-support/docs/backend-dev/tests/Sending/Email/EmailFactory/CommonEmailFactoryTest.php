<?php

namespace App\Tests\Sending\Email\EmailFactory;

use App\Companies\Models\Company;
use App\Sending\Email\EmailFactory\CommonEmailFactory;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\ValueObjects\Email;
use App\Sending\Email\ValueObjects\NamedAddress;

class CommonEmailFactoryTest extends AbstractEmailFactoryTestBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::$company->name = 'Test Company';
        self::$company->email = 'test@example.com';
        self::$company->logo = 'LOGO';
        self::$company->save();

        self::hasCustomer();
        self::hasInvoice();
        self::hasInbox();
        self::hasEmailThread();
    }

    public function testGetCompany(): void
    {
        $message = $this->getMessage(self::$thread);
        $this->assertInstanceOf(Company::class, $message->getCompany());
    }

    public function testFrom(): void
    {
        $message = $this->getMessage(self::$thread);
        $this->assertEquals(new NamedAddress('test@example.com', 'Test Company'), $message->getFrom());
    }

    public function getFactory(): CommonEmailFactory
    {
        return new CommonEmailFactory('test.invoicedmail.com');
    }

    public function testPlainText(): void
    {
        $message = $this->getMessage(self::$thread);
        $this->assertEquals('test', $message->getPlainText());
    }

    public function testHtml(): void
    {
        $message = $this->getMessage(self::$thread);
        $html = $message->getHtml();
        $this->assertNull($html);
    }

    public function testAttachments(): void
    {
        $message = $this->getMessage(self::$thread);
        $this->assertEquals([], $message->getAttachments());
    }

    public function testHeaders(): void
    {
        $message = $this->getMessage(self::$thread);

        $expected = [
            'Reply-To' => 'Test Company <'.self::$inbox->external_id.'@test.invoicedmail.com>',
            'X-Invoiced' => 'true',
            'X-Invoiced-Account' => self::$company->identifier,
            'X-Auto-Response-Suppress' => 'All',
            'Message-ID' => '<'.self::$company->username.'/'.$message->getId().'@invoiced.com>',
            'References' => '',
        ];

        $this->assertEquals($expected, $message->getHeaders());
    }

    public function testHeadersNewThread(): void
    {
        $message = $this->getMessage(null);

        $expected = [
            'Reply-To' => 'Test Company <'.self::$inbox->external_id.'@test.invoicedmail.com>',
            'X-Invoiced' => 'true',
            'X-Invoiced-Account' => self::$company->identifier,
            'X-Auto-Response-Suppress' => 'All',
            'Message-ID' => '<'.self::$company->username.'/'.$message->getId().'@invoiced.com>',
        ];

        $this->assertEquals($expected, $message->getHeaders());
    }

    private function getMessage(?EmailThread $emailThread): Email
    {
        $factory = $this->getFactory();

        return $factory->make(
            inbox: self::$inbox,
            to: [['email_address' => 'test@example.com', 'name' => 'Test']],
            cc: [],
            bcc: [],
            subject: 'Test',
            message: 'test',
            status: EmailThread::STATUS_OPEN,
            thread: $emailThread,
            replyToId: null,
            relatedToType: null,
            relatedToId: null,
            attachments: [],
        );
    }
}
