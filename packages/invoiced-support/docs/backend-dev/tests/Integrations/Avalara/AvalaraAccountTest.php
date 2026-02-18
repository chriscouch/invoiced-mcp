<?php

namespace App\Tests\Integrations\Avalara;

use App\Integrations\Avalara\AvalaraAccount;
use App\Tests\AppTestCase;

class AvalaraAccountTest extends AppTestCase
{
    private static AvalaraAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new AvalaraAccount();
        self::$account->company_code = 'client_id';
        self::$account->name = 'Test';
        self::$account->license_key = 'shhh';
        self::$account->account_id = 'user';
        $this->assertTrue(self::$account->save());

        // verify access token encryption
        $this->assertEquals('shhh', self::$account->license_key);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->license_key = 'password2';
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
