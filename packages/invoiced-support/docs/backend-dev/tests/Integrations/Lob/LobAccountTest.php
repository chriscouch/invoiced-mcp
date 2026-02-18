<?php

namespace App\Tests\Integrations\Lob;

use App\Integrations\Lob\LobAccount;
use App\Tests\AppTestCase;

class LobAccountTest extends AppTestCase
{
    private static LobAccount $account;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$account = new LobAccount();
        self::$account->key = 'secret';
        $this->assertTrue(self::$account->save());

        // verify auth token encryption
        $this->assertEquals('secret', self::$account->key);
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'created_at' => self::$account->created_at,
            'updated_at' => self::$account->updated_at,
            'custom_envelope' => null,
            'return_envelopes' => false,
            'use_color' => false,
        ];
        $this->assertEquals($expected, self::$account->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$account->key = '123456789';
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
