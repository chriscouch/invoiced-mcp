<?php

namespace App\Tests\Integrations\ChartMogul;

use App\Integrations\ChartMogul\Models\ChartMogulAccount;
use App\Tests\AppTestCase;

class ChartMogulAccountTest extends AppTestCase
{
    private static ChartMogulAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new ChartMogulAccount();
        self::$account->token = 'shhh';
        self::$account->data_source = 'data source id';
        $this->assertTrue(self::$account->save());

        // verify access token encryption
        $this->assertEquals('shhh', self::$account->token);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->last_sync_attempt = time();
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
