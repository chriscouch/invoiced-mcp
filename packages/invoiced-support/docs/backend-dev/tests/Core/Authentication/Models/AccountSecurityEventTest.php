<?php

namespace App\Tests\Core\Auth\Models;

use App\Core\Authentication\Models\AccountSecurityEvent;
use App\Core\Authentication\Models\User;
use App\Tests\AppTestCase;

class AccountSecurityEventTest extends AppTestCase
{
    private static User $user;
    private static AccountSecurityEvent $event;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::getService('test.database')->delete('Users', ['email' => 'test+auth@example.com']);

        self::$user = new User();
        self::$user->email = 'test+auth@example.com';
        self::$user->password = ['TestPassw0rd!', 'TestPassw0rd!']; /* @phpstan-ignore-line */
        self::$user->ip = '127.0.0.1';
        self::$user->first_name = 'Bob';
        self::$user->last_name = 'Loblaw';
        self::$user->saveOrFail();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$user->delete();
    }

    public function testCreate(): void
    {
        self::$event = new AccountSecurityEvent();
        self::$event->user_id = (int) self::$user->id();
        self::$event->type = 'user.login';
        self::$event->auth_strategy = 'web';
        self::$event->ip = '127.0.0.1';
        self::$event->user_agent = 'Firefox';
        $this->assertTrue(self::$event->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$event->id(),
            'type' => 'user.login',
            'ip' => '127.0.0.1',
            'user_agent' => 'Firefox',
            'auth_strategy' => 'web',
            'description' => '',
            'created_at' => self::$event->created_at,
            'updated_at' => self::$event->updated_at,
        ];

        $this->assertEquals($expected, self::$event->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$event->delete());
    }
}
