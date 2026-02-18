<?php

namespace App\Tests\Integrations\Twilio;

use App\Integrations\Twilio\TwilioAccount;
use App\Tests\AppTestCase;

class TwilioAccountTest extends AppTestCase
{
    private static TwilioAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new TwilioAccount();
        self::$account->account_sid = '1234';
        self::$account->auth_token = 'secret';
        $this->assertTrue(self::$account->save());

        // verify auth token encryption
        $this->assertEquals('secret', self::$account->auth_token);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->from_number = '123456789';
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
