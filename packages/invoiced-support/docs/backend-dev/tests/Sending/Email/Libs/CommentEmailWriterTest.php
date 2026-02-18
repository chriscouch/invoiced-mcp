<?php

namespace App\Tests\Sending\Email\Libs;

use App\Notifications\Enums\NotificationEventType;
use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Libs\CommentEmailWriter;
use App\Sending\Email\Storage\NullBodyStorage;
use App\Tests\AppTestCase;

class CommentEmailWriterTest extends AppTestCase
{
    public function testWrite(): void
    {
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();

        $spool = \Mockery::mock(NotificationSpool::class);
        $writer = new CommentEmailWriter(new NullBodyStorage(), self::getService('test.database'), $spool);
        $spool->shouldReceive('spool')->withSomeOfArgs(NotificationEventType::EmailReceived, self::$company->id, self::$invoice->customer)->once();
        $writer->write(self::$invoice, true, 'test', 'test', []);
    }
}
