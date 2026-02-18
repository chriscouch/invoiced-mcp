<?php

namespace App\Tests\Core\Auth\Models;

use App\Core\Authentication\Models\User;
use App\Core\Authentication\Models\UserLink;
use App\Tests\AppTestCase;

class UserLinkTest extends AppTestCase
{
    private static User $user;
    private static UserLink $link;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
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
        self::$link = new UserLink();
        self::$link->user_id = (int) self::$user->id();
        self::$link->type = UserLink::FORGOT_PASSWORD;
        $this->assertTrue(self::$link->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$link->delete());
    }
}
