<?php

namespace App\Tests\Integrations\NetSuite\Models;

use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Tests\AppTestCase;

class NetSuiteAccountTest extends AppTestCase
{
    private static NetSuiteAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new NetSuiteAccount();
        self::$account->account_id = 'org-id';
        self::$account->token = 'user';
        self::$account->token_secret = 'password';
        $this->assertTrue(self::$account->save());

        // verify access token encryption
        $this->assertEquals('user', self::$account->token);
        $this->assertEquals('password', self::$account->token_secret);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->token_secret = 'password2';
        $this->assertTrue(self::$account->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$account->delete());
    }
}
