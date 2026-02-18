<?php

namespace App\Tests\Core\Auth\Models;

use App\Core\Authentication\Models\ActiveSession;
use App\Core\Authentication\Models\User;
use App\Tests\AppTestCase;

class ActiveSessionTest extends AppTestCase
{
    private static User $user;
    private static ActiveSession $session;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::getService('test.database')->delete('Users', ['email' => 'test+auth@example.com']);
        self::getService('test.database')->delete('ActiveSessions', ['id' => 'sesh_1234']);

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
        self::$session = new ActiveSession();
        self::$session->id = 'sesh_1234';
        self::$session->user_id = (int) self::$user->id();
        self::$session->ip = '127.0.0.1';
        self::$session->user_agent = 'Firefox';
        self::$session->expires = strtotime('+1 day');
        $this->assertTrue(self::$session->save());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => 'sesh_1234',
            'ip' => '127.0.0.1',
            'user_agent' => 'Firefox',
            'expires' => self::$session->expires,
            'created_at' => self::$session->created_at,
            'updated_at' => self::$session->updated_at,
        ];

        $this->assertEquals($expected, self::$session->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$session->expires = strtotime('+2 days');
        $this->assertTrue(self::$session->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$session->delete());
    }
}
