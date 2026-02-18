<?php

namespace App\Tests\Sending\Email\Models;

use App\Sending\Email\Models\SmtpAccount;
use App\Tests\AppTestCase;

class SmtpAccountTest extends AppTestCase
{
    private static SmtpAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new SmtpAccount();
        self::$account->host = 'smtp.invoiced.com';
        self::$account->username = 'jared@invoiced.com';
        self::$account->password = 'shhh';
        self::$account->port = 587;
        self::$account->encryption = 'tls';
        self::$account->auth_mode = 'login';
        $this->assertTrue(self::$account->save());

        // verify access token encryption
        $this->assertEquals('shhh', self::$account->password);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->password = 'password2';
        $this->assertTrue(self::$account->save());
    }

    public function testToArray(): void
    {
        $expected = [
            'auth_mode' => 'login',
            'encryption' => 'tls',
            'host' => 'smtp.invoiced.com',
            'port' => 587,
            'username' => 'jared@invoiced.com',
            'fallback_on_failure' => true,
            'last_error_message' => null,
            'last_error_timestamp' => null,
            'last_send_successful' => null,
            'created_at' => self::$account->created_at,
            'updated_at' => self::$account->updated_at,
        ];

        $this->assertEquals($expected, self::$account->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$account->delete());
    }

    public function testToDsn(): void
    {
        $account = new SmtpAccount([
            'host' => 'host',
            'username' => 'username',
            'password' => 'password',
            'port' => 1234,
            'encryption' => 'tls',
            'auth_mode' => 'login',
        ]);

        $dsn = $account->toDsn();
        $this->assertEquals('host', $dsn->getHost());
        $this->assertEquals('username', $dsn->getUser());
        $this->assertEquals('password', $dsn->getPassword());
        $this->assertEquals(1234, $dsn->getPort());
        $this->assertEquals('smtp', $dsn->getScheme());
    }

    public function testToDsnSSL(): void
    {
        $account = new SmtpAccount([
            'host' => 'host',
            'username' => 'username',
            'password' => 'password',
            'port' => 1234,
            'encryption' => 'ssl',
            'auth_mode' => 'login',
        ]);

        $dsn = $account->toDsn();
        $this->assertEquals('host', $dsn->getHost());
        $this->assertEquals('username', $dsn->getUser());
        $this->assertEquals('password', $dsn->getPassword());
        $this->assertEquals(1234, $dsn->getPort());
        $this->assertEquals('smtps', $dsn->getScheme());
    }
}
