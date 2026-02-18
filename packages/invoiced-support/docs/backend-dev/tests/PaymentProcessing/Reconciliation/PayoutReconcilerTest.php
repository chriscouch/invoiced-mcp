<?php

namespace App\Tests\PaymentProcessing\Reconciliation;

use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Enums\PayoutStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\Payout;
use App\PaymentProcessing\Reconciliation\MerchantAccountLedger;
use App\PaymentProcessing\Reconciliation\PayoutReconciler;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Money\Currency;
use Money\Money;

class PayoutReconcilerTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasMerchantAccount(AdyenGateway::ID);
    }

    private function getOperation(MerchantAccountLedger $ledger): PayoutReconciler
    {
        return new PayoutReconciler(self::getService('test.transaction_manager'), $ledger, self::getService('test.event_spool'));
    }

    private function getLedger(): MerchantAccountLedger
    {
        return self::getService('test.merchant_account_ledger');
    }

    public function testReconcile(): void
    {
        EventSpool::enable();
        $ledger = $this->getLedger();
        $operation = $this->getOperation($ledger);

        $payout = $operation->reconcile(self::$merchantAccount, [
            'amount' => 1000,
            'arrival_date' => CarbonImmutable::now()->addDays(2),
            'bank_account_name' => 'Chase *1234',
            'currency' => 'usd',
            'description' => 'Test Payout',
            'initiated_at' => CarbonImmutable::now(),
            'reference' => 'ABC123',
            'status' => PayoutStatus::Completed,
        ]);

        // Validate payout
        $this->assertInstanceOf(Payout::class, $payout);
        $this->assertEquals([
            'amount' => 1000,
            'arrival_date' => $payout->arrival_date,
            'bank_account_name' => 'Chase *1234',
            'created_at' => $payout->created_at,
            'currency' => 'usd',
            'description' => 'Test Payout',
            'failure_message' => null,
            'gross_amount' => 1000,
            'id' => $payout->id,
            'initiated_at' => $payout->initiated_at,
            'merchant_account_id' => self::$merchantAccount->id,
            'merchant_account_transaction_id' => $payout->merchant_account_transaction?->id,
            'object' => 'payout',
            'pending_amount' => 0,
            'reference' => 'ABC123',
            'statement_descriptor' => null,
            'status' => 'completed',
            'updated_at' => $payout->updated_at,
            'modification_reference' => null
        ], $payout->toArray());

        // Validate merchant account transaction
        $transaction = $payout->merchant_account_transaction;
        $this->assertNotNull($transaction);
        $this->assertEquals([
            'amount' => -1000.0,
            'available_on' => $payout->initiated_at,
            'created_at' => $transaction->created_at,
            'currency' => 'usd',
            'description' => 'Test Payout',
            'fee' => 0.0,
            'fee_details' => [],
            'id' => $transaction->id,
            'merchant_account_id' => self::$merchantAccount->id,
            'net' => -1000.0,
            'reference' => 'ABC123',
            'source_id' => $payout->id,
            'source_type' => 'payout',
            'type' => 'payout',
            'updated_at' => $transaction->updated_at,
            'object' => 'merchant_account_transaction',
            'merchant_reference' => null,
            'payout_id' => null,
        ], $transaction->toArray());

        // Validate ledger entries
        $ledger = $this->getLedger()->getLedger(self::$merchantAccount);
        $balances = $ledger->reporting->getAccountBalances(CarbonImmutable::now());
        $this->assertEquals([
            [
                'name' => 'Bank Account',
                'balance' => new Money(100000, new Currency('usd')),
            ],
            [
                'name' => 'Disputed Payments',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Merchant Account',
                'balance' => new Money(-100000, new Currency('usd')),
            ],
            [
                'name' => 'Processed Payments',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Processing Fees',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Refunded Payments',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Rounding Difference',
                'balance' => new Money(0, new Currency('usd')),
            ],
        ], $balances);

        // Validate activity log entry
        $this->assertHasEvent($payout, EventType::PayoutCreated);
    }

    /**
     * @depends testReconcile
     */
    public function testReconcileFailed(): void
    {
        EventSpool::enable();
        $ledger = $this->getLedger();
        $operation = $this->getOperation($ledger);

        $payout = $operation->reconcile(self::$merchantAccount, [
            'amount' => 1000,
            'arrival_date' => CarbonImmutable::now()->addDays(2),
            'bank_account_name' => 'Chase *1234',
            'currency' => 'usd',
            'description' => 'Test Payout',
            'initiated_at' => CarbonImmutable::now(),
            'reference' => 'ABC123',
            'status' => PayoutStatus::Failed,
        ]);

        // Validate payout
        $this->assertInstanceOf(Payout::class, $payout);
        $this->assertEquals([
            'amount' => 1000,
            'arrival_date' => $payout->arrival_date,
            'bank_account_name' => 'Chase *1234',
            'created_at' => $payout->created_at,
            'currency' => 'usd',
            'description' => 'Test Payout',
            'failure_message' => null,
            'gross_amount' => 1000,
            'id' => $payout->id,
            'initiated_at' => $payout->initiated_at,
            'merchant_account_id' => self::$merchantAccount->id,
            'merchant_account_transaction_id' => $payout->merchant_account_transaction?->id,
            'object' => 'payout',
            'pending_amount' => 0,
            'reference' => 'ABC123',
            'statement_descriptor' => null,
            'status' => 'failed',
            'updated_at' => $payout->updated_at,
            'modification_reference' => null
        ], $payout->toArray());

        // Validate merchant account transaction
        $transaction = MerchantAccountTransaction::where('reference', 'ABC123-reversal')
            ->where('type', MerchantAccountTransactionType::PayoutReversal->value)
            ->oneOrNull();
        $this->assertNotNull($transaction);
        $this->assertEquals([
            'amount' => 1000.0,
            'available_on' => $payout->initiated_at->setTime(0, 0), /* @phpstan-ignore-line */
            'created_at' => $transaction->created_at,
            'currency' => 'usd',
            'description' => 'Test Payout',
            'fee' => 0.0,
            'fee_details' => [],
            'id' => $transaction->id,
            'merchant_account_id' => self::$merchantAccount->id,
            'net' => 1000.0,
            'payout_id' => null,
            'reference' => 'ABC123-reversal',
            'source_id' => $payout->id,
            'source_type' => 'payout',
            'type' => 'payout_reversal',
            'updated_at' => $transaction->updated_at,
            'object' => 'merchant_account_transaction',
            'merchant_reference' => null,
        ], $transaction->toArray());

        // Validate ledger entries
        $ledger = $this->getLedger()->getLedger(self::$merchantAccount);
        $balances = $ledger->reporting->getAccountBalances(CarbonImmutable::now());
        $this->assertEquals([
            [
                'name' => 'Bank Account',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Disputed Payments',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Merchant Account',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Processed Payments',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Processing Fees',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Refunded Payments',
                'balance' => new Money(0, new Currency('usd')),
            ],
            [
                'name' => 'Rounding Difference',
                'balance' => new Money(0, new Currency('usd')),
            ],
        ], $balances);

        // Validate activity log entry
        $this->assertHasEvent($payout, EventType::PayoutFailed);
    }
}
