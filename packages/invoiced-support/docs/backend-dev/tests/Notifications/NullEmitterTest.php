<?php

namespace App\Tests\Notifications;

use App\ActivityLog\Models\Event;
use App\Notifications\Emitters\NullEmitter;
use App\Tests\AppTestCase;

class NullEmitterTest extends AppTestCase
{
    private function getEmitter(): NullEmitter
    {
        return new NullEmitter();
    }

    public function testEmit(): void
    {
        $event = new Event();
        $this->assertTrue($this->getEmitter()->emit($event, self::getService('test.user_context')->get()));
    }
}
