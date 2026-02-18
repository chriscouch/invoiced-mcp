<?php

namespace App\Tests\PaymentProcessing\Operations;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\StatsdClient;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\ChargeApplicationType;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Gateways\AbstractGateway;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Libs\PaymentGatewayMetadata;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\InitiatedCharge;
use App\PaymentProcessing\Models\InitiatedChargeDocument;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\Reconciliation\ChargeReconciler;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\CreditNoteChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use App\Tests\AppTestCase;
use Mockery;
use Monolog\Logger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

class ProcessPaymentTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasMerchantAccount('test');
        self::$customer->cc_gateway = self::$merchantAccount;
    }

    public function testInitiatedCharge(): void
    {
        $invoice = self::getTestDataFactory()->createInvoice(self::$customer);
        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $chargeReconciller = Mockery::mock(ChargeReconciler::class);
        $lockFactory = $this->getLockFactory();
        $processor = $this->getOperation($gatewayFactory, $lockFactory, $chargeReconciller);

        $gatewayMock = Mockery::mock(AbstractGateway::class);
        $gatewayMock->shouldReceive('validateConfiguration');
        $gatewayMock->shouldReceive('charge')->andThrow(new ChargeException())->once();
        $gatewayFactory->shouldReceive('get')->andReturn($gatewayMock);

        $split = new InvoiceChargeApplicationItem(Money::fromDecimal('usd', 100), $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $method = new PaymentMethod();
        $method->id = PaymentMethod::CREDIT_CARD;
        try {
            $processor->pay($method, self::$customer, $chargeApplication, [], null);
            $this->assertTrue(false, 'No exception thrown');
        } catch (ChargeException $e) {
        }

        $gatewayMock->shouldReceive('charge')->andReturn(new ChargeValueObject(
            customer: new Customer(),
            amount: Money::fromDecimal('usd', 100), // We are intentionally treating pending as successful
            gateway: PaymentGatewayMetadata::PAYPAL,
            gatewayId: 'test',
            method: PaymentMethod::PAYPAL,
            status: Transaction::STATUS_SUCCEEDED,
            merchantAccount: null,
            source: null,
            description: '',
        ))->twice();
        $e = new ReconciliationException('test');
        $chargeReconciller->shouldReceive('reconcile')->andThrow($e)->once();
        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('emergency')->withArgs([
            'Unable to reconcile charge when processing payment', ['exception' => $e],
        ]);
        $processor->setLogger($logger);
        try {
            $processor->pay($method, self::$customer, $chargeApplication, [], null);
            $this->assertFalse(true, 'No exception thrown');
        } catch (ChargeException $e) {
            $this->assertEquals('Your payment was successfully processed but could not be saved. Please do not retry payment.', $e->getMessage());
        }

        $charges = InitiatedCharge::execute();
        $this->assertCount(1, $charges);
        $this->assertNotNull($charges[0]->correlation_id);
        $this->assertEquals(100, $charges[0]->amount);
        $this->assertEquals('usd', $charges[0]->currency);

        $documents = InitiatedChargeDocument::execute();
        $this->assertCount(1, $documents);
        $this->assertEquals($invoice->id, $documents[0]->document_id);
        $this->assertEquals(ChargeApplicationType::InvoiceChargeApplicationItem->value, $documents[0]->document_type);
        $this->assertEquals($charges[0]->id, $documents[0]->initiated_charge_id);

        try {
            $processor->pay($method, self::$customer, $chargeApplication, [], null);
            $this->assertTrue(false, 'No exception thrown');
        } catch (ChargeException) {
        }

        $processor->setMutexLock([]);
        InitiatedCharge::query()->delete();
        InitiatedChargeDocument::query()->delete();
        $chargeReconciller->shouldReceive('reconcile')->andReturn(null)->once();
        $processor->pay($method, self::$customer, $chargeApplication, [
            'foo' => 'bar',
        ], null);
        $charges = InitiatedCharge::execute();
        $this->assertCount(0, $charges);
    }

    public function testInitiatedChargeWithSource(): void
    {
        $invoice = self::getTestDataFactory()->createInvoice(self::$customer);
        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $chargeReconciller = Mockery::mock(ChargeReconciler::class);
        $lockFactory = $this->getLockFactory();
        $processor = $this->getOperation($gatewayFactory, $lockFactory, $chargeReconciller);

        $gatewayMock = Mockery::mock(AbstractGateway::class);
        $gatewayMock->shouldReceive('validateConfiguration');
        $gatewayMock->shouldReceive('chargeSource')->andThrow(new ChargeException())->once();
        $gatewayFactory->shouldReceive('get')->andReturn($gatewayMock);

        $split = new InvoiceChargeApplicationItem(Money::fromDecimal('usd', 100), $invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        self::hasCard();
        $method = self::$card;
        try {
            $processor->payWithSource($method, $chargeApplication, [], null);
            $this->assertTrue(false, 'No exception thrown');
        } catch (ChargeException) {
        }

        $gatewayMock->shouldReceive('chargeSource')->andReturn(new ChargeValueObject(
            customer: new Customer(),
            amount: Money::fromDecimal('usd', 100), // We are intentionally treating pending as successful
            gateway: PaymentGatewayMetadata::PAYPAL,
            gatewayId: 'test',
            method: '',
            status: Transaction::STATUS_SUCCEEDED,
            merchantAccount: null,
            source: null,
            description: '',
        ))->twice();
        $e = new ReconciliationException('test');
        $chargeReconciller->shouldReceive('reconcile')->andThrow($e)->once();
        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('emergency')->withArgs([
            'Unable to reconcile charge when processing payment', ['exception' => $e],
        ]);
        $processor->setLogger($logger);
        try {
            $processor->payWithSource($method, $chargeApplication, [], null);
            $this->assertFalse(true, 'No exception thrown');
        } catch (ChargeException $e) {
            $this->assertEquals('Your payment was successfully processed but could not be saved. Please do not retry payment.', $e->getMessage());
        }

        $charges = InitiatedCharge::execute();
        $this->assertCount(1, $charges);
        $this->assertNotNull($charges[0]->correlation_id);
        $this->assertEquals(100, $charges[0]->amount);
        $this->assertEquals('usd', $charges[0]->currency);

        $documents = InitiatedChargeDocument::execute();
        $this->assertCount(1, $documents);
        $this->assertEquals($invoice->id, $documents[0]->document_id);
        $this->assertEquals(ChargeApplicationType::InvoiceChargeApplicationItem->value, $documents[0]->document_type);
        $this->assertEquals($charges[0]->id, $documents[0]->initiated_charge_id);

        // test duplicate payment exception
        try {
            $processor->payWithSource($method, $chargeApplication, [], null);
            $this->assertTrue(false, 'No exception thrown');
        } catch (ChargeException) {
        }

        $method->receipt_email = 'test@test.com';
        $method->saveOrFail();
        $processor->setMutexLock([]);
        InitiatedCharge::query()->delete();
        InitiatedChargeDocument::query()->delete();
        $chargeReconciller->shouldReceive('reconcile')->andReturn(null)->once();
        $processor->payWithSource($method, $chargeApplication, [], null);
        $charges = InitiatedCharge::execute();
        $this->assertCount(0, $charges);
        $this->assertEquals('test@test.com', $method->receipt_email);
    }

    public function testHandleSuccessPaymentWithChargeMethodNull(): void
    {
        self::hasCard();
        self::hasMerchantAccount((string) self::$card->gateway_id);
        $invoice = self::getTestDataFactory()->createInvoice(self::$customer);
        $creditNote = self::getTestDataFactory()->createCreditNote(self::$customer);

        $gateway = Mockery::mock(AbstractGateway::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('charge')->andReturn(new ChargeValueObject(
            customer: self::$customer,
            amount: Money::fromDecimal('usd', 10000),
            gateway: 'mock',
            gatewayId: 'test',
            method: '',
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: null,
            description: '',
        ));
        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);
        $chargeReconciller = Mockery::mock(ChargeReconciler::class);
        $lockFactory = $this->getLockFactory();
        $processor = $this->getOperation($gatewayFactory, $lockFactory, $chargeReconciller);
        $processor->setMutexLock([]);

        $split = new InvoiceChargeApplicationItem(new Money('usd', 50), $invoice);
        $split2 = new CreditNoteChargeApplicationItem(new Money('usd', 50), $creditNote, $invoice);
        $chargeApplication = new ChargeApplication([$split, $split2], PaymentFlowSource::Charge);
        $initiatedCharge = Mockery::mock(InitiatedCharge::class);
        $initiatedCharge->shouldReceive('delete');

        $method = new PaymentMethod();
        $method->id = 'credit_card';

        $chargeReconciller->shouldReceive('reconcile')->withArgs(fn (ChargeValueObject $charge) => 'test' === $charge->gatewayId && 'credit_card' === $charge->method)
            ->andReturn(new Charge(['payment' => new Payment()]));
        $payment = $processor->pay($method, self::$customer, $chargeApplication, [], null);

        $this->assertInstanceOf(Payment::class, $payment);
    }

    private function getLockFactory(): LockFactory
    {
        return new LockFactory(new FlockStore());
    }

    private function getOperation(PaymentGatewayFactory $gatewayFactory, LockFactory $lockFactory, ChargeReconciler $chargeReconciller): ProcessPayment
    {
        $processor = new ProcessPayment(
            $gatewayFactory,
            $lockFactory,
            $chargeReconciller,
            self::getService('test.initiated_charge_factory'),
            self::getService('test.gateway_logger')
        );
        $logger = Mockery::mock(Logger::class);
        $processor->setLogger($logger);

        $processor->setStatsd(new StatsdClient());

        return $processor;
    }
}
