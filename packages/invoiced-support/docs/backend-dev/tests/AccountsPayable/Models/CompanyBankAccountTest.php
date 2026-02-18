<?php

namespace App\Tests\AccountsPayable\Models;

use App\AccountsPayable\Enums\CheckStock;
use App\AccountsPayable\Models\CompanyBankAccount;
use App\Integrations\Plaid\Models\PlaidItem;
use App\PaymentProcessing\Models\AchFileFormat;
use App\Tests\AppTestCase;

class CompanyBankAccountTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCreate(): void
    {
        $plaid = $this->createPlaid();

        $account = new CompanyBankAccount();
        $account->name = 'test3';
        $account->plaid = $plaid;
        $account->saveOrFail();
    }

    private function createPlaid(): PlaidItem
    {
        $plaid = new PlaidItem();
        $plaid->item_id = 'item_id';
        $plaid->access_token = 'tok_test';
        $plaid->institution_name = 'Chase';
        $plaid->institution_id = 'ins_3';
        $plaid->account_id = 'account_id';
        $plaid->account_name = 'Chase Checking';
        $plaid->account_last4 = '3333';
        $plaid->account_type = 'depository';
        $plaid->account_subtype = 'checking';
        $plaid->saveOrFail();

        return $plaid;
    }

    public function testPaymentMethods(): void
    {
        $account = new CompanyBankAccount();
        $this->assertEquals([], $account->getPaymentMethods());

        $account->check_number = 1;
        $account->check_layout = CheckStock::CheckOnTop;
        $this->assertEquals(['print_check'], $account->getPaymentMethods());

        $account->account_number = '123456';
        $account->routing_number = '110000000';
        $this->assertEquals(['echeck', 'print_check'], $account->getPaymentMethods());

        $account->ach_file_format = new AchFileFormat();
        $this->assertEquals(['ach', 'echeck', 'print_check'], $account->getPaymentMethods());
    }
}
