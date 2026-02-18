<?php

namespace App\Tests\Inbox\Models;

use App\Sending\Email\Models\Inbox;
use App\Sending\Email\Models\InboxDecorator;
use App\Tests\AppTestCase;

class InboxDecoratorTest extends AppTestCase
{
    public function testToArray(): void
    {
        self::hasCompany();
        $inbox = new Inbox();
        $inbox->external_id = 'test';
        $inboxDecorator = new InboxDecorator($inbox, 'emaildomain.com');
        $this->assertEquals([
            'created_at' => null,
            'external_id' => 'test',
            'id' => false,
            'email' => 'test@emaildomain.com',
            'updated_at' => null,
        ], $inboxDecorator->toArray());
    }
}
