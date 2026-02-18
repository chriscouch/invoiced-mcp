<?php

namespace App\Tests\AccountsPayable\Libs;

use App\AccountsPayable\Libs\CompanyBankAccountSave;
use App\AccountsPayable\Models\CompanyBankAccount;
use App\Integrations\Plaid\Libs\PlaidApi;
use App\Tests\AppTestCase;
use Mockery;

class CompanyBankAccountSaveTest extends AppTestCase
{
    public static CompanyBankAccountSave $save;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        $plaidApi = Mockery::mock(PlaidApi::class);
        self::$save = new CompanyBankAccountSave($plaidApi);
    }

    public function testUpdateDefault(): void
    {
        $account = new CompanyBankAccount();
        $account->name = 'test1';
        self::$save->save($account);
        $this->assertEquals(0, $account->default);

        $account2 = new CompanyBankAccount();
        $account2->name = 'test2';
        $account2->default = true;
        self::$save->save($account2);
        $this->assertEquals(0, $account->refresh()->default);
        $this->assertEquals(1, $account2->refresh()->default);

        $account->name = 'test1';
        self::$save->save($account);
        $this->assertEquals(0, $account->refresh()->default);
        $this->assertEquals(1, $account2->refresh()->default);

        $account->default = true;
        self::$save->save($account);
        $this->assertEquals(1, $account->refresh()->default);
        $this->assertEquals(0, $account2->refresh()->default);
    }
}
