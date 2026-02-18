<?php

namespace App\Tests\Sending\Email\Libs;

use App\Notifications\Libs\NotificationSpool;
use App\Sending\Email\Libs\BounceEmailWriter;
use App\Sending\Email\Storage\NullBodyStorage;
use App\Tests\AppTestCase;
use Mockery;

class BounceEmailWriterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInbox();
        self::hasEmailThread();
        self::hasInboxEmail();
    }

    public function testWrite(): void
    {
        $spool = Mockery::mock(NotificationSpool::class);
        $spool->shouldReceive('spool')->once();
        $writer = new BounceEmailWriter(new NullBodyStorage(), self::getService('test.database'), $spool);

        $writer->write('test', 'test', self::$inboxEmail);
    }

    public function testWriteWithCustomer(): void
    {
        $spool = Mockery::mock(NotificationSpool::class);
        $spool->shouldReceive('spool')->once();
        $writer = new BounceEmailWriter(new NullBodyStorage(), self::getService('test.database'), $spool);

        self::$thread->customer = self::$customer;
        self::$thread->saveOrFail();
        $writer->write('test', 'test', self::$inboxEmail);
    }
}
