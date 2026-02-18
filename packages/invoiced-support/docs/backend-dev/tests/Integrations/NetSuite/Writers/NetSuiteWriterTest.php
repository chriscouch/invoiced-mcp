<?php

namespace App\Tests\Integrations\NetSuite\Writers;

use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\Statsd\StatsdClient;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingTransactionMapping;
use App\Integrations\AccountingSync\Models\ReconciliationError;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\NetSuite\Libs\NetSuiteApi;
use App\Integrations\NetSuite\Writers\NetSuiteTransactionPaymentWriter;
use App\Integrations\NetSuite\Writers\NetSuiteWriter;
use App\Integrations\NetSuite\Writers\NetSuiteWriterFactory;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Event\ModelDeleted;
use App\Core\Orm\Event\ModelUpdated;

class NetSuiteWriterTest extends AbstractWriterTestCase
{
    private static AccountingSyncProfile $profile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasNetSuiteCustomer();
        self::hasNetSuiteAccount();
        self::$netsuiteAccount->account_id = 'account_id';
        self::$netsuiteAccount->token = 'token';
        self::$netsuiteAccount->token_secret = 'token_secret';
        self::$netsuiteAccount->saveOrFail();
        self::$profile = new AccountingSyncProfile();
        self::$profile->integration = IntegrationType::NetSuite;
        self::$profile->saveOrFail();
    }

    private function getWriter(?string $id = '1234'): NetSuiteWriter
    {
        $api = \Mockery::mock(NetSuiteApi::class);
        $api->shouldReceive('callRestlet')
            ->andReturn((object) ['id' => $id]);
        $factory = new NetSuiteWriterFactory();
        $writer = new NetSuiteWriter($api, $factory);
        $writer->setStatsd(new StatsdClient());

        return $writer;
    }

    public function testCreateTransaction(): void
    {
        $invoice = $this->hasNetsuiteInvoice();
        $transaction = $this->createTransaction($invoice, 1);
        $this->assertTrue($transaction->isReconcilable());

        $record = new NetSuiteTransactionPaymentWriter($transaction);

        $this->assertEquals([
            'amount' => 1.0,
            'custbody_invoiced_id' => $transaction->id(),
            'customer' => 1,
            'invoices' => [
                [
                    'id' => 3,
                    'amount' => 1,
                    'type' => Transaction::TYPE_PAYMENT,
                ],
            ],
            'checknum' => null,
            'gateway' => null,
            'payment_source' => null,
            'type' => 'payment',
            'payment' => null,
        ], $record->toArray());

        $this->assertTrue($record->shouldCreate());

        $invoice2 = $this->hasNetsuiteInvoice();
        $transaction = $this->createTransaction($invoice, 3);
        $transaction2 = $this->createTransaction($invoice2, 2, $transaction);
        $this->assertFalse($transaction2->isReconcilable());

        $record = new NetSuiteTransactionPaymentWriter($transaction);
        $this->assertTrue($record->shouldCreate());
        $this->assertFalse((new NetSuiteTransactionPaymentWriter($transaction2))->shouldCreate());

        /** @var array $result */
        $result = $record->toArray();
        $this->assertNotNull($result);
        $this->assertEquals(5.0, $result['amount']);
        $this->assertEquals($transaction->id(), $result['custbody_invoiced_id']);
        $this->assertEquals('1', $result['customer']);
        $this->assertEquals([
            [
                'id' => 3,
                'amount' => 3.0,
                'type' => Transaction::TYPE_PAYMENT,
            ],
            [
                'id' => 3,
                'amount' => 2.0,
                'type' => Transaction::TYPE_PAYMENT,
            ],
        ], $result['invoices']);
    }

    public function testCreatePayment(): void
    {
        self::hasNetSuiteCustomer();
        self::hasNetSuiteInvoice();
        $payment = new Payment();
        $payment->customer = self::$customer->id;
        $payment->amount = 200;
        $payment->currency = 'usd';
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => self::$invoice,
                'amount' => 20,
            ],
        ];
        $payment->saveOrFail();

        $this->assertTrue($payment->isReconcilable());

        $writer = $this->getWriter();
        $factory = new NetSuiteWriterFactory();
        $nsModel = $factory->create($payment, new AccountingSyncProfile());
        $this->assertTrue($nsModel->shouldCreate());
        $writer->create($payment, self::$netsuiteAccount, self::$profile);
        $nsModel = $factory->create($payment, new AccountingSyncProfile());
        $this->assertFalse($nsModel->shouldCreate());

        $cnt = AccountingTransactionMapping::where('accounting_id', 1234)->count();
        $this->assertEquals(0, $cnt);
        $cnt = AccountingPaymentMapping::where('accounting_id', 1234)->count();
        $this->assertEquals(1, $cnt);
        $this->assertFalse($payment->isReconcilable());
    }

    public function testAPI(): void
    {
        $cnt = AccountingTransactionMapping::where('accounting_id', 1234)->count();
        $this->assertEquals(0, $cnt);

        $invoice = $this->hasNetsuiteInvoice();
        $transaction = $this->createTransaction($invoice, 1);

        $writer = $this->getWriter(null);
        $writer->create($transaction, self::$netsuiteAccount, self::$profile);

        $writer = $this->getWriter();
        $writer->create($transaction, self::$netsuiteAccount, self::$profile);

        $cnt = AccountingTransactionMapping::where('accounting_id', 1234)->count();
        $this->assertEquals(1, $cnt);
    }

    public function testExceptionHandling(): void
    {
        $model = new Transaction();
        $adapter = \Mockery::mock(NetSuiteTransactionPaymentWriter::class);
        $api = \Mockery::mock(NetSuiteApi::class);
        $factory = \Mockery::mock(NetSuiteWriterFactory::class);
        $factory->shouldReceive('create')->andReturn($adapter)->times(6);
        $writer = new class($api, $factory) extends NetSuiteWriter {
            public string $lastModelName;

            public function handleSyncException(AccountingWritableModelInterface $record, IntegrationType $integrationType, string $message, string $eventName, string $level = ReconciliationError::LEVEL_ERROR): void
            {
                $this->lastModelName = $eventName;
            }
        };

        $adapter->shouldReceive('shouldCreate')->andReturnFalse()->once();
        $adapter->shouldReceive('shouldUpdate')->andReturnFalse()->once();
        $adapter->shouldReceive('shouldDelete')->andReturnFalse()->once();
        $adapter->shouldNotHaveReceived('toArray');
        $writer->create($model, self::$netsuiteAccount, self::$profile);
        $writer->update($model, self::$netsuiteAccount, self::$profile);
        $writer->delete($model, self::$netsuiteAccount, self::$profile);

        $adapter->shouldReceive('shouldCreate')->andReturnTrue()->once();
        $adapter->shouldReceive('shouldUpdate')->andReturnTrue()->once();
        $adapter->shouldReceive('shouldDelete')->andReturnTrue()->once();
        $adapter->shouldReceive('toArray')->andThrow(new IntegrationApiException())->twice();
        $writer->create($model, self::$netsuiteAccount, self::$profile);
        $this->assertEquals(ModelCreated::getName(), $writer->lastModelName);
        $writer->update($model, self::$netsuiteAccount, self::$profile);
        $this->assertEquals(ModelUpdated::getName(), $writer->lastModelName);
        $adapter->shouldReceive('getReverseMapping')->andThrow(new IntegrationApiException())->once();
        $writer->delete($model, self::$netsuiteAccount, self::$profile);
        $this->assertEquals(ModelDeleted::getName(), $writer->lastModelName);

        $adapter = \Mockery::mock(NetSuiteTransactionPaymentWriter::class);
        $adapter->shouldReceive('shouldCreate')->andReturnTrue()->once();
        $adapter->shouldReceive('shouldUpdate')->andReturnTrue()->once();
        $adapter->shouldReceive('shouldDelete')->andReturnTrue()->once();
        $adapter->shouldReceive('skipReconciliation')->times(3);
        $factory->shouldReceive('create')->andReturn($adapter)->times(3);
        $error = [
            'error' => [
                'code' => 'SSS_INVALID_SCRIPTLET_ID',
            ],
        ];
        $error = new IntegrationApiException((string) json_encode($error));
        $adapter->shouldReceive('toArray')->andThrow($error)->twice();
        $writer->create($model, self::$netsuiteAccount, self::$profile);
        $writer->update($model, self::$netsuiteAccount, self::$profile);
        $adapter->shouldReceive('getReverseMapping')->andThrow($error)->once();
        $writer->delete($model, self::$netsuiteAccount, self::$profile);
    }
}
