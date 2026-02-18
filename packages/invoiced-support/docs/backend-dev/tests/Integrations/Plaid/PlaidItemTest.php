<?php

namespace App\Tests\Integrations\Plaid;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Tests\AppTestCase;

class PlaidItemTest extends AppTestCase
{
    private static PlaidItem $plaidItem;

    public static function setUpBeforeClass(): void
    {
        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::$plaidItem = new PlaidItem();
        self::$plaidItem->item_id = 'item_id';
        self::$plaidItem->access_token = 'tok_test';
        self::$plaidItem->institution_name = 'Chase';
        self::$plaidItem->institution_id = 'ins_3';
        self::$plaidItem->account_id = 'account_id';
        self::$plaidItem->account_name = 'Chase Checking';
        self::$plaidItem->account_last4 = '3333';
        self::$plaidItem->account_type = 'depository';
        self::$plaidItem->account_subtype = 'checking';
        $this->assertTrue(self::$plaidItem->save());

        $cashApp = new CashApplicationBankAccount();
        $cashApp->data_starts_at = time();
        $cashApp->plaid_link = self::$plaidItem;
        $cashApp->saveOrFail();

        // verify access token encryption
        $this->assertEquals('tok_test', self::$plaidItem->access_token);
    }

    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        self::$plaidItem->access_token = 'tok_test_2';
        $this->assertTrue(self::$plaidItem->save());
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$plaidItem->delete());
        $this->assertTrue(self::$plaidItem->deleted);
        $this->assertTrue(self::$plaidItem->persisted());
    }
}
