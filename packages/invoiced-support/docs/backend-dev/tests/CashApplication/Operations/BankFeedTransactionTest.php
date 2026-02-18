<?php

namespace App\Tests\CashApplication\Operations;

use App\CashApplication\Models\BankFeedTransaction;
use App\CashApplication\Models\CashApplicationBankAccount;
use App\CashApplication\Models\CashApplicationRule;
use App\CashApplication\Models\Payment;
use App\CashApplication\Operations\CreateBankFeedTransaction;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use App\PaymentProcessing\Models\PaymentMethod;

class BankFeedTransactionTest extends AppTestCase
{
    private static CashApplicationBankAccount $cashApplicationBankAccount;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();

        $plaidItem = new PlaidItem();
        $plaidItem->access_token = 'tok_test';
        $plaidItem->account_id = '123';
        $plaidItem->saveOrFail();
        self::$cashApplicationBankAccount = new CashApplicationBankAccount();
        self::$cashApplicationBankAccount->last_retrieved_data_at = 1;
        self::$cashApplicationBankAccount->plaid_link = $plaidItem;
        self::$cashApplicationBankAccount->data_starts_at = 1;
        self::$cashApplicationBankAccount->saveOrFail();
    }

    public function testCreateRuleIgnore(): void
    {
        $this->makeRule('transaction.description contains "Test"', true, '', null);
        $operation = $this->getOperation();

        $bankFeedTransaction = new BankFeedTransaction();
        $bankFeedTransaction->transaction_id = 'testCreateRuleIgnore';
        $bankFeedTransaction->amount = -200;
        $bankFeedTransaction->description = 'ORIG CO NAME: Test';
        $bankFeedTransaction->date = CarbonImmutable::now();
        $bankFeedTransaction->cash_application_bank_account = self::$cashApplicationBankAccount;
        $bankFeedTransaction->payment_reference_number = '1234';

        $result = $operation->create($bankFeedTransaction);

        $this->assertTrue($result->persisted());
        $this->assertTrue($bankFeedTransaction->persisted());
        $this->assertEquals($bankFeedTransaction->id, $result->id);

        // Should NOT create a payment
        $payment = Payment::where('bank_feed_transaction_id', $bankFeedTransaction)->oneOrNull();
        $this->assertNull($payment);
    }

    public function testCreateRuleSetMethod(): void
    {
        $this->makeRule('transaction.payment_method == "ACH"', false, 'ach', null);
        $operation = $this->getOperation();

        $bankFeedTransaction = new BankFeedTransaction();
        $bankFeedTransaction->transaction_id = 'testCreateRuleSetMethod';
        $bankFeedTransaction->amount = -200;
        $bankFeedTransaction->description = 'ORIG CO NAME: Random Payer';
        $bankFeedTransaction->payment_method = 'ACH';
        $bankFeedTransaction->date = CarbonImmutable::now();
        $bankFeedTransaction->cash_application_bank_account = self::$cashApplicationBankAccount;
        $bankFeedTransaction->payment_reference_number = '1234';

        $result = $operation->create($bankFeedTransaction);

        $this->assertTrue($result->persisted());
        $this->assertTrue($bankFeedTransaction->persisted());
        $this->assertEquals($bankFeedTransaction->id, $result->id);

        // Should create a payment
        $payment = Payment::where('bank_feed_transaction_id', $bankFeedTransaction)->one();
        $this->assertNull($payment->customer);
        $this->assertEquals(200, $payment->amount);
        $this->assertEquals(Payment::SOURCE_BANK_FEED, $payment->source);
        $this->assertEquals('1234', $payment->reference);
        $this->assertEquals(PaymentMethod::ACH, $payment->method);
    }

    public function testCreateRuleSetCustomer(): void
    {
        $this->makeRule('transaction.payment_payer == "Test Payer"', false, '', self::$customer->id);
        $operation = $this->getOperation();

        $bankFeedTransaction = new BankFeedTransaction();
        $bankFeedTransaction->transaction_id = 'testCreateRuleSetCustomer';
        $bankFeedTransaction->amount = -200;
        $bankFeedTransaction->description = 'ORIG CO NAME: PAYABLES SYSTEM';
        $bankFeedTransaction->payment_payer = 'Test Payer';
        $bankFeedTransaction->date = CarbonImmutable::now();
        $bankFeedTransaction->cash_application_bank_account = self::$cashApplicationBankAccount;
        $bankFeedTransaction->payment_reference_number = '1234';

        $result = $operation->create($bankFeedTransaction);

        $this->assertTrue($result->persisted());
        $this->assertTrue($bankFeedTransaction->persisted());
        $this->assertEquals($bankFeedTransaction->id, $result->id);

        // Should create a payment
        $payment = Payment::where('bank_feed_transaction_id', $bankFeedTransaction)->one();
        $this->assertEquals(self::$customer->id, $payment->customer);
        $this->assertEquals(200, $payment->amount);
        $this->assertEquals(Payment::SOURCE_BANK_FEED, $payment->source);
        $this->assertEquals('1234', $payment->reference);
        $this->assertEquals(PaymentMethod::OTHER, $payment->method);
    }

    public function testCreateNoRules(): void
    {
        $operation = $this->getOperation();

        $bankFeedTransaction = new BankFeedTransaction();
        $bankFeedTransaction->transaction_id = 'testCreateNoRules';
        $bankFeedTransaction->amount = -200;
        $bankFeedTransaction->description = 'ORIG CO NAME: Invoiced, Inc.';
        $bankFeedTransaction->date = CarbonImmutable::now();
        $bankFeedTransaction->cash_application_bank_account = self::$cashApplicationBankAccount;
        $bankFeedTransaction->payment_reference_number = '1234';

        $result = $operation->create($bankFeedTransaction);

        $this->assertTrue($result->persisted());
        $this->assertTrue($bankFeedTransaction->persisted());
        $this->assertEquals($bankFeedTransaction->id, $result->id);

        // Should create a payment
        $payment = Payment::where('bank_feed_transaction_id', $bankFeedTransaction)->one();
        $this->assertNull($payment->customer);
        $this->assertEquals(200, $payment->amount);
        $this->assertEquals(Payment::SOURCE_BANK_FEED, $payment->source);
        $this->assertEquals('1234', $payment->reference);
        $this->assertEquals(PaymentMethod::OTHER, $payment->method);
    }

    public function testCreatePreventDuplicate(): void
    {
        $operation = $this->getOperation();

        $bankFeedTransaction = new BankFeedTransaction();
        $bankFeedTransaction->transaction_id = 'testCreateNoRules';
        $bankFeedTransaction->amount = -200;
        $bankFeedTransaction->description = 'ORIG CO NAME: Invoiced, Inc.';
        $bankFeedTransaction->date = CarbonImmutable::now();
        $bankFeedTransaction->cash_application_bank_account = self::$cashApplicationBankAccount;

        $result = $operation->create($bankFeedTransaction);

        $this->assertTrue($result->persisted());
        $this->assertFalse($bankFeedTransaction->persisted());
    }

    private function getOperation(): CreateBankFeedTransaction
    {
        return self::getService('test.create_bank_feed_transaction');
    }

    private function makeRule(string $formula, bool $ignore, string $method, ?int $customer): void
    {
        $rule = new CashApplicationRule();
        $rule->formula = $formula;
        $rule->ignore = $ignore;
        $rule->method = $method;
        $rule->customer = $customer;
        $rule->saveOrFail();
    }
}
