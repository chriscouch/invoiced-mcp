<?php

namespace App\Tests\Integrations\Xero;

use App\Integrations\Xero\Models\XeroAccount;
use App\Tests\AppTestCase;

class XeroAccountTest extends AppTestCase
{
    private static XeroAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new XeroAccount();
        self::$account->access_token = 'tok_test';
        self::$account->expires = strtotime('+30 minutes');
        $this->assertTrue(self::$account->save());

        // verify access token encryption
        $this->assertEquals('tok_test', self::$account->access_token);
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
