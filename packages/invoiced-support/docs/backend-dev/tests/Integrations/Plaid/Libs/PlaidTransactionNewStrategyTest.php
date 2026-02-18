<?php

namespace App\Tests\Integrations\Plaid\Libs;

use App\CashApplication\Models\BankFeedTransaction;
use App\CashApplication\Models\CashApplicationBankAccount;
use App\CashApplication\Models\Payment;
use App\CashApplication\Operations\CreateBankFeedTransaction;
use App\Integrations\Plaid\Libs\PlaidTransactionExtractor;
use App\Integrations\Plaid\Libs\PlaidTransactionProcessor;
use App\Integrations\Plaid\Libs\PlaidTransactionTransformer;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionHistoricalStrategy;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionNewStrategy;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Generator;
use Mockery;

class PlaidTransactionNewStrategyTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        parent::hasCompany();
    }

    private function getTransactions(): Generator
    {
        return yield from [
            (object) [
                'date' => '2024-06-22',
            ],
        ];
    }

    public function testProcessVoided(): void
    {
        $account = new CashApplicationBankAccount();
        $account->last_retrieved_data_at = 1;
        $plaidItem = new PlaidItem();
        $plaidItem->access_token = 'tok_test';
        $plaidItem->account_id = '123';
        $plaidItem->saveOrFail();
        $account->plaid_link = $plaidItem;
        $account->data_starts_at = 1;

        $bankFeedTransaction = new BankFeedTransaction();
        $bankFeedTransaction->transaction_id = 'TEST';
        $bankFeedTransaction->amount = -200;
        $bankFeedTransaction->description = 'Test';
        $bankFeedTransaction->date = CarbonImmutable::now();
        $bankFeedTransaction->cash_application_bank_account = $account;

        $extractor = Mockery::mock(PlaidTransactionExtractor::class);

        $extractor->shouldReceive('extract')->andReturnUsing(function () {
            return $this->getTransactions();
        });
        $transformer = Mockery::mock(PlaidTransactionTransformer::class);
        $transformer->shouldReceive('transform')->andReturn($bankFeedTransaction);
        $loader = $this->getOperation();
        $processor = new PlaidTransactionProcessor($extractor, $transformer, $loader);
        $strategy = new PlaidTransactionHistoricalStrategy($processor);

        $strategy->process(['webhook_code' => 'HISTORICAL_UPDATE'], $account);
        $payments = Payment::query()->execute();
        $this->assertCount(1, $payments);
        $this->assertEquals(200, $payments[0]->amount);
        $this->assertEquals($bankFeedTransaction->id, $payments[0]->bank_feed_transaction?->id);

        $strategy = new PlaidTransactionNewStrategy($processor);
        $strategy->process(['webhook_code' => 'DEFAULT_UPDATE'], $account);

        $bankFeedTransaction = BankFeedTransaction::one();
        $expected = [
            'amount' => -200.0,
            'cash_application_bank_account_id' => $account->id,
            'check_number' => null,
            'created_at' => $bankFeedTransaction->created_at,
            'date' => $bankFeedTransaction->date,
            'description' => 'Test',
            'id' => $bankFeedTransaction->id,
            'merchant_name' => null,
            'payment_by_order_of' => null,
            'payment_channel' => null,
            'payment_method' => null,
            'payment_payee' => null,
            'payment_payer' => null,
            'payment_ppd_id' => null,
            'payment_processor' => null,
            'payment_reason' => null,
            'payment_reference_number' => null,
            'transaction_id' => 'TEST',
            'updated_at' => $bankFeedTransaction->updated_at,
        ];
        $result = $bankFeedTransaction->toArray();
        $this->assertEquals($expected, $result);

        // Should not create any new payments
        $payments = Payment::query()->execute();
        $this->assertCount(1, $payments);
        $this->assertEquals(200, $payments[0]->amount);
        $this->assertEquals($bankFeedTransaction->id, $payments[0]->bank_feed_transaction?->id);
    }

    private function getOperation(): CreateBankFeedTransaction
    {
        return self::getService('test.create_bank_feed_transaction');
    }
}
