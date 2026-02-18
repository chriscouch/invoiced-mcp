<?php

namespace App\Tests\Sending\Email\Adapter;

use App\Sending\Email\Interfaces\AdapterInterface;
use App\Sending\Email\ValueObjects\DocumentEmail;
use App\Sending\Email\ValueObjects\EmailAttachment;
use App\Sending\Email\ValueObjects\NamedAddress;
use App\Tests\AppTestCase;

abstract class AbstractAdapterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    abstract protected function getAdapter(): AdapterInterface;

    /**
     * @doesNotPerformAssertions
     */
    public function testSend(): void
    {
        $email = $this->getEmail();
        $this->getAdapter()->send($email);
    }

    protected function getEmail(bool $html = true): DocumentEmail
    {
        $attachment1 = new EmailAttachment('Test.pdf', 'application/pdf', '__content__');
        $attachment1->setSize(100);
        $attachment2 = new EmailAttachment('Too Large.pdf', 'application/pdf', '__content__');
        $attachment2->setSize(31457280);

        $email = new DocumentEmail('TEST');
        $email->company(self::$company)
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

        if ($html) {
            $email->html('html');
        }

        return $email;
    }
}
