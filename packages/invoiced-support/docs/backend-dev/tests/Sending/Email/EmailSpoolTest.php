<?php

namespace App\Tests\Sending\Email;

use App\Sending\Email\EmailFactory\DocumentEmailFactory;
use App\Sending\Email\Interfaces\EmailInterface;
use App\Sending\Email\Libs\EmailSender;
use App\Sending\Email\Libs\EmailSpool;
use App\Tests\AppTestCase;
use Mockery;

class EmailSpoolTest extends AppTestCase
{
    public function testSpool(): void
    {
        $spool = $this->getSpool();
        $spool->spool(Mockery::mock(EmailInterface::class));
        $this->assertEquals(1, $spool->size());
        $spool->clear();
        $this->assertEquals(0, $spool->size());
    }

    public function testFlush(): void
    {
        $sender = Mockery::mock(EmailSender::class);
        $sender->shouldReceive('send')->twice();

        $spool = new EmailSpool($sender, Mockery::mock(DocumentEmailFactory::class));
        $spool->spool(Mockery::mock(EmailInterface::class));
        $spool->spool(Mockery::mock(EmailInterface::class));
        $spool->flush();
    }

    public function testDestructor(): void
    {
        $sender = Mockery::mock(EmailSender::class);
        $sender->shouldReceive('send')->twice();

        $spool = new EmailSpool($sender, Mockery::mock(DocumentEmailFactory::class));
        $spool->spool(Mockery::mock(EmailInterface::class));
        $spool->spool(Mockery::mock(EmailInterface::class));
        // destroying the object should flush out events
        $spool = null;
    }

    private function getSpool(): EmailSpool
    {
        return self::getService('test.email_spool');
    }
}
