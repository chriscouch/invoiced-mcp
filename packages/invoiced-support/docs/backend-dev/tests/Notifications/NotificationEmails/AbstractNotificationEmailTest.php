<?php

namespace App\Tests\Notifications\NotificationEmails;

use App\Notifications\Models\NotificationEvent;
use App\Tests\AppTestCase;

abstract class AbstractNotificationEmailTest extends AppTestCase
{
    /** @var NotificationEvent[] */
    protected static array $events;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$events = [];
    }
}
