<?php

namespace App\Tests\Integrations\Intacct;

use App\Integrations\Intacct\Models\IntacctAccount;
use App\Tests\AppTestCase;

class IntacctAccountTest extends AppTestCase
{
    private static IntacctAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new IntacctAccount();
        self::$account->intacct_company_id = 'org-id';
        self::$account->user_id = 'user';
        self::$account->user_password = 'password';
        $this->assertTrue(self::$account->save());

        // verify access token encryption
        $this->assertEquals('password', self::$account->user_password);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->user_password = 'password2';
        $this->assertTrue(self::$account->save());
    }

    /**
     * @depends testCreate
     */
    public function testCannotUseInvoicedSenderId(): void
    {
        self::$account->sender_id = 'Invoiced';
        self::$account->sender_password = 'sender_password';
        $this->assertFalse(self::$account->save());

        self::$account->intacct_company_id = 'Invoiced-DEV SE';
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
