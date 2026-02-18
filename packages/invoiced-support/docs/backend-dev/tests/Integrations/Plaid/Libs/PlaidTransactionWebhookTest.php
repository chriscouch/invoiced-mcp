<?php

namespace App\Tests\Integrations\Plaid\Libs;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\CashApplication\Models\Payment;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhook;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionErrorStrategy;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionHistoricalStrategy;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionLoginRepairedStrategy;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionNewStrategy;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionPendingExpirationStrategy;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionRemoveStrategy;
use App\Integrations\Plaid\Libs\PlaidTransactionWebhookStrategy\PlaidTransactionWebhookStrategyInterface;
use App\Integrations\Plaid\Models\PlaidItem;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;

class PlaidTransactionWebhookTest extends AppTestCase
{
    private static PlaidTransactionWebhook $webhook;
    private static PlaidItem $plaidItem;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        parent::hasCompany();

        self::$webhook = self::getService('test.plaid_transaction_webhook');
        $plaidItem = new PlaidItem();
        $plaidItem->item_id = 'item_id'.time();
        $plaidItem->access_token = 'tok_test';
        $plaidItem->institution_name = 'Chase';
        $plaidItem->institution_id = 'ins_3';
        $plaidItem->account_id = 'account_id';
        $plaidItem->account_name = 'Chase Checking';
        $plaidItem->account_last4 = '3333';
        $plaidItem->account_type = 'depository';
        $plaidItem->account_subtype = 'checking';
        $plaidItem->saveOrFail();
        self::$plaidItem = $plaidItem;

        $cashApp = new CashApplicationBankAccount();
        $cashApp->data_starts_at = CarbonImmutable::now()->subDays(31)->unix();
        $cashApp->plaid_link = $plaidItem;
        $cashApp->last_retrieved_data_at = 1;
        $cashApp->saveOrFail();

        self::$webhook->getCompanies([
            'item_id' => $plaidItem->item_id,
        ]);
    }

    public function testProcessInitial(): void
    {
        $this->expectException(IntegrationApiException::class);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'INITIAL_UPDATE',
        ]);
    }

    public function testProcessHistorical(): void
    {
        $this->expectException(IntegrationApiException::class);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'HISTORICAL_UPDATE',
        ]);
    }

    public function testProcessDefault(): void
    {
        $this->expectException(IntegrationApiException::class);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'DEFAULT_UPDATE',
        ]);
    }

    public function testProcessRemoved(): void
    {
        $payment = new Payment();
        $payment->amount = 200;
        $payment->currency = 'usd';
        $payment->external_id = 'ch_'.time();
        $payment->source = Payment::SOURCE_BANK_FEED;
        $payment->saveOrFail();

        self::$webhook->process(self::$company, [
            'webhook_code' => 'TRANSACTIONS_REMOVED',
            'removed_transactions' => [
                $payment->external_id,
            ],
        ]);
        $this->assertTrue($payment->refresh()->voided);
    }

    public function testProcessExpired(): void
    {
        self::$plaidItem->needs_update = false;
        self::$plaidItem->saveOrFail();
        self::$webhook->process(self::$company, [
            'webhook_code' => 'PENDING_EXPIRATION',
        ]);
        $this->assertTrue(self::$plaidItem->refresh()->needs_update);
    }

    public function testProcessError(): void
    {
        self::$plaidItem->needs_update = false;
        self::$plaidItem->saveOrFail();
        self::$webhook->process(self::$company, [
            'webhook_code' => 'ERROR',
        ]);
        $this->assertTrue(self::$plaidItem->refresh()->needs_update);
    }

    public function testProcessRepaired(): void
    {
        self::$plaidItem->needs_update = true;
        self::$plaidItem->saveOrFail();
        self::$webhook->process(self::$company, [
            'webhook_code' => 'LOGIN_REPAIRED',
        ]);
        $this->assertFalse(self::$plaidItem->refresh()->needs_update);
    }

    public function testProcess(): void
    {
        /** @var PlaidTransactionWebhookStrategyInterface[] $mocks */
        $mocks = array_map(function ($class) {
            $mock = Mockery::mock($class);
            $mock->makePartial();
            $mock->shouldReceive('process')->once();

            return $mock;
        }, [
            PlaidTransactionHistoricalStrategy::class,
            PlaidTransactionNewStrategy::class,
            PlaidTransactionRemoveStrategy::class,
            PlaidTransactionPendingExpirationStrategy::class,
            PlaidTransactionErrorStrategy::class,
            PlaidTransactionLoginRepairedStrategy::class,
        ]);
        $mocks[] = new class() implements PlaidTransactionWebhookStrategyInterface {
            public function process(array $event, CashApplicationBankAccount $bankAccount): void
            {
                throw new \Exception('Should not been called');
            }

            public function match(string $webhook_code): bool
            {
                return false;
            }
        };

        self::$webhook->setStrategies(...$mocks);

        self::$webhook->process(self::$company, [
            'webhook_code' => 'HISTORICAL_UPDATE',
        ]);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'DEFAULT_UPDATE',
        ]);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'TRANSACTIONS_REMOVED',
        ]);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'PENDING_EXPIRATION',
        ]);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'ERROR',
        ]);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'LOGIN_REPAIRED',
        ]);
        self::$webhook->process(self::$company, [
            'webhook_code' => 'wrong',
        ]);
        $this->assertTrue(true);
    }
}
