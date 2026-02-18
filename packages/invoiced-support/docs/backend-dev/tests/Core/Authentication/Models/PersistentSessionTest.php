<?php

namespace App\Tests\Core\Auth\Models;

use App\Core\Authentication\Models\PersistentSession;
use App\Core\Authentication\Models\User;
use App\Tests\AppTestCase;

class PersistentSessionTest extends AppTestCase
{
    private static User $user;
    private static PersistentSession $sesh;

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
        self::$sesh = new PersistentSession();
        self::$sesh->token = '969326B47C4994ADAF57AD7CE7345D5A40F1F9565DE899E8302DA903340E5A79969326B47C4994ADAF57AD7CE7345D5A40F1F9565DE899E8302DA903340E5A79';
        self::$sesh->email = 'test+auth@example.com';
        self::$sesh->series = 'DeFx724Iqo6LwbJK4JB1MGXEbHpe9p3MNKZONqellNrBuWbytxGr7nPU5VwI3VwDeFx724Iqo6LwbJK4JB1MGXEbHpe9p3MNKZONqellNrBuWbytxGr7nPU5VwI3Vwff';
        self::$sesh->user_id = (int) self::$user->id();
        $this->assertTrue(self::$sesh->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$sesh->delete());
    }
}
