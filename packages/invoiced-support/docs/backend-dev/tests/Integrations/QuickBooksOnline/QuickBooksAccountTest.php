<?php

namespace App\Tests\Integrations\QuickBooksOnline;

use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Tests\AppTestCase;

class QuickBooksAccountTest extends AppTestCase
{
    private static QuickBooksAccount $account;

    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new QuickBooksAccount();
        self::$account->realm_id = 'realm_id';
        self::$account->access_token = 'tok_test';
        self::$account->refresh_token = 'refresh';
        self::$account->expires = strtotime('+60 minutes');
        self::$account->refresh_token_expires = strtotime('+100 days');
        $this->assertTrue(self::$account->save());

        // verify access token encryption
        $this->assertEquals('tok_test', self::$account->access_token);
        $this->assertEquals('refresh', self::$account->refresh_token);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->access_token = 'tok_test_2';
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
