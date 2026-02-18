<?php

namespace App\Tests\Integrations\Slack;

use App\Integrations\Slack\SlackAccount;
use App\Tests\AppTestCase;

class SlackAccountTest extends AppTestCase
{
    private static SlackAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new SlackAccount();
        self::$account->team_id = 'client_id';
        self::$account->name = 'Test';
        self::$account->access_token = 'shhh';
        self::$account->webhook_url = 'http://example.com';
        self::$account->webhook_config_url = 'http://example.com/config';
        self::$account->webhook_channel = '#test';
        $this->assertTrue(self::$account->save());

        // verify access token encryption
        $this->assertEquals('shhh', self::$account->access_token);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->access_token = 'password2';
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
