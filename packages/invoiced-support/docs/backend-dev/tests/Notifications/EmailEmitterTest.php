<?php

namespace App\Tests\Notifications;

use App\ActivityLog\Models\Event;
use App\Notifications\Emitters\EmailEmitter;
use App\Tests\AppTestCase;

class EmailEmitterTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    private function getEmitter(): EmailEmitter
    {
        return self::getService('test.notification_job')->getEmitter('email');
    }

    public function testEmit(): void
    {
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->type = 'invoice.created';
        $event->object = (object) [];
        $this->assertTrue($this->getEmitter()->emit($event, self::getService('test.user_context')->get()));
    }

    public function testDoesNotEmitSameUser(): void
    {
        $event = new Event();
        $event->tenant_id = (int) self::$company->id();
        $event->user_id = (int) self::getService('test.user_context')->get()->id();
        $event->object = (object) [];
        $this->assertFalse($this->getEmitter()->emit($event, self::getService('test.user_context')->get()));
    }
}
