<?php

namespace App\Tests\Sending\Email\Adapter;

use App\Companies\Models\Company;
use App\Sending\Email\Adapter\InvoicedInboxAdapter;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\ValueObjects\DocumentEmail;
use App\Sending\Email\ValueObjects\NamedAddress;

class InvoicedInboxAdapterTest extends AbstractAdapterTest
{
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasInbox();
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (isset(self::$company2)) {
            self::$company2->delete();
        }
    }

    public function testInvalidInbox(): void
    {
        $this->expectException(SendEmailException::class);
        $this->expectExceptionMessage('The mailbox you tried to reach does not exist: doesnotexist@test.invoicedmail.com');

        $email = $this->getEmail(false);
        $email->to([new NamedAddress('doesnotexist@test.invoicedmail.com')]);
        $this->getAdapter()->send($email);
    }

    public function testSend(): void
    {
        parent::testSend();

        self::getService('test.tenant')->runAs(self::$company2, function () {
            $emailThread = EmailThread::where('inbox_id', self::$inbox)->oneOrNull();
            $this->assertInstanceOf(EmailThread::class, $emailThread);
            $this->assertEquals(EmailThread::STATUS_OPEN, $emailThread->status);
            $this->assertEquals('Subject', $emailThread->name);
            $this->assertNull($emailThread->related_to_type);
            $this->assertNull($emailThread->related_to_id);

            $emails = $emailThread->emails;
            $this->assertCount(1, $emails);
            $email = $emails[0];
            $this->assertEquals('<test@invoiced.com>', $email->message_id);
            $this->assertEquals('Subject', $email->subject);
            $this->assertNull($email->reply_to_email_id);
            $this->assertTrue($email->incoming);
            $this->assertEquals([
                'name' => 'TEST',
                'email_address' => 'test@example.com',
            ], $email->from);
            $this->assertEquals([
                [
                    'name' => 'TO Recipient',
                    'email_address' => self::$inbox->external_id.'@test.invoicedmail.com',
                ],
            ], $email->to);
            $this->assertEquals([], $email->cc);
            $this->assertEquals([], $email->bcc);
        });
    }

    protected function getAdapter(): InvoicedInboxAdapter
    {
        return self::getService('test.email_invoiced_inbox_adapter');
    }

    protected function getEmail(bool $html = true): DocumentEmail
    {
        return parent::getEmail($html)
            ->to([new NamedAddress(self::$inbox->external_id.'@test.invoicedmail.com', 'TO Recipient')])
            ->cc([])
            ->bcc([])
            ->attachments([]);
    }
}
