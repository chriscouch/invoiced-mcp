<?php

namespace App\Tests\PaymentProcessing\Forms;

use App\AccountsReceivable\Libs\CustomerHierarchy;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Note;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Chasing\Models\PromiseToPay;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\StatsdClient;
use App\CustomerPortal\Libs\CustomerPortal;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\PaymentProcessing\Enums\PaymentAmountOption;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\FormException;
use App\PaymentProcessing\Forms\PaymentFormBuilder;
use App\PaymentProcessing\Forms\PaymentFormProcessor;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\OneTimeChargeInterface;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountRouting;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\PaymentProcessing\Operations\VaultPaymentInfo;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\Tests\AppTestCase;
use Mockery;

class PaymentFormProcessorTest extends AppTestCase
{
    private static Invoice $invoice2;
    private static PaymentMethod $paymentMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::acceptsCreditCards();
        self::hasCustomer();
        self::hasInvoice();
        self::hasMerchantAccount('stripe');

        self::$invoice2 = new Invoice();
        self::$invoice2->setCustomer(self::$customer);
        self::$invoice2->items = [['unit_cost' => 200]];
        self::$invoice2->saveOrFail();

        $paidInvoice = new Invoice();
        $paidInvoice->setCustomer(self::$customer);
        $paidInvoice->items = [['unit_cost' => 100]];
        $paidInvoice->amount_paid = 100;
        $paidInvoice->saveOrFail();

        $pendingInvoice = new Invoice();
        $pendingInvoice->setCustomer(self::$customer);
        $pendingInvoice->items = [['unit_cost' => 100]];
        $pendingInvoice->saveOrFail();

        $payment = new Transaction();
        $payment->setInvoice($pendingInvoice);
        $payment->amount = $pendingInvoice->balance;
        $payment->status = Transaction::STATUS_PENDING;
        $payment->saveOrFail();

        $voidedInvoice = new Invoice();
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 100]];
        $voidedInvoice->voided = true;
        $voidedInvoice->saveOrFail();

        self::$paymentMethod = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
    }

    protected function tearDown(): void
    {
        self::getService('test.process_payment')->setGatewayFactory(self::getService('test.payment_gateway_factory'));
    }

    private function getFormBuilder(?PaymentFormSettings $settings = null): PaymentFormBuilder
    {
        if ($settings) {
            self::$company->customer_portal_settings->allow_partial_payments = $settings->allowPartialPayments;
            self::$company->customer_portal_settings->allow_invoice_payment_selector = $settings->allowApplyingCredits;
            self::$company->customer_portal_settings->allow_advance_payments = $settings->allowAdvancePayments;
            self::$company->customer_portal_settings->allow_autopay_enrollment = $settings->allowAutoPayEnrollment;
        }
        $portal = new CustomerPortal(self::$company, new CustomerHierarchy(self::getService('test.database')));
        $portal->setSignedInCustomer(self::$customer);

        return new PaymentFormBuilder($portal);
    }

    private function getFormProcessor(): PaymentFormProcessor
    {
        return self::getService('test.payment_form_processor');
    }

    private function getFormProcessorForGateway(PaymentGatewayInterface $gateway): PaymentFormProcessor
    {
        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);

        $processPayment = self::getService('test.process_payment');
        $processPayment->setGatewayFactory($gatewayFactory);
        $reconciler = new PaymentSourceReconciler();
        $reconciler->setStatsd(new StatsdClient());
        $gatewayLogger = self::getService('test.gateway_logger');
        $deletePaymentInfo = new DeletePaymentInfo($gatewayFactory, $gatewayLogger);
        $deletePaymentInfo->setStatsd(new StatsdClient());
        $vaultPaymentInfo = new VaultPaymentInfo($reconciler, $gatewayFactory, $deletePaymentInfo, $gatewayLogger);
        $vaultPaymentInfo->setStatsd(new StatsdClient());

        $events = Mockery::mock(CustomerPortalEvents::class);
        $events->shouldReceive('track');

        $form = new PaymentFormProcessor($vaultPaymentInfo, $deletePaymentInfo, $processPayment, self::getService('test.notification_spool'), $events);
        $form->setStatsd(new StatsdClient());

        return $form;
    }

    private function makePaymentFlow(PaymentForm $form): PaymentFlow
    {
        $manager = self::getService('test.payment_flow_manager');
        $flow = new PaymentFlow();
        $flow->amount = $form->totalAmount->toDecimal();
        $flow->currency = $form->currency;
        $flow->customer = $form->customer;
        $flow->initiated_from = PaymentFlowSource::CustomerPortal;
        $manager->create($flow);

        return $flow;
    }

    public function testHandleSubmitLocked(): void
    {
        $this->expectException(FormException::class);
        $this->expectExceptionMessage('Duplicate payment attempt detected.');

        $invoice = new Invoice(['id' => -2]);
        $invoice->number = '1';
        $invoice->currency = 'usd';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->balance = 100;
        $invoice->setCustomer(self::$customer);

        self::getService('test.redis')->setnx('invoiced.localhost:payment_lock.invoice.-2', 60);

        $builder = $this->getFormBuilder();
        $builder->addInvoice($invoice);

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        $this->getFormProcessor()->handleSubmit($form, $parameters);
    }

    public function testHandleSubmitEmpty(): void
    {
        // Setup - Models, Mocks, etc.

        $this->expectException(FormException::class);
        $this->expectExceptionMessage('Payment cannot be empty');

        $processor = $this->getFormProcessor();

        $settings = new PaymentFormSettings(
            self::$company,
            true,
            false,
            false,
            false
        );

        $invoice = new Invoice(['id' => -3]);
        $invoice->number = '1';
        $invoice->currency = 'usd';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);

        $builder = $this->getFormBuilder($settings);
        $builder->addInvoice($invoice);

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested

        $processor->handleSubmit($form, $parameters);
    }

    public function testHandleSubmitAmountTooSmall(): void
    {
        self::$paymentMethod->setMerchantAccount(self::$merchantAccount);

        // Setup - Models, Mocks, etc.
        $settings = new PaymentFormSettings(
            self::$company,
            true,
            false,
            false,
            false
        );

        $invoice = new Invoice(['id' => -3]);
        $invoice->number = '1';
        $invoice->currency = 'usd';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->balance = .25;
        $invoice->setCustomer(self::$customer);

        $builder = $this->getFormBuilder($settings);
        $builder->addInvoice($invoice);
        $builder->setMethod(self::$paymentMethod);

        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested
        try {
            $this->getFormProcessor()->handleSubmit($form, $parameters);
            $this->assertTrue(false, 'No Exception fired');
        } catch (FormException $e) {
            $this->assertEquals('Payment amount cannot be less than 0.5 USD', $e->getMessage());
        } finally {
            self::$paymentMethod->gateway = 'mock';
        }
    }

    public function testHandleSubmitMissingRequiredCvc(): void
    {
        // Setup - Models, Mocks, etc.

        self::$company->accounts_receivable_settings->saved_cards_require_cvc = true;
        self::$company->accounts_receivable_settings->saveOrFail();

        $this->expectException(FormException::class);
        $this->expectExceptionMessage('Please enter in the required CVC code');

        $invoice = new Invoice(['id' => -4]);
        $invoice->number = '1';
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->balance = 100;
        $invoice->tenant_id = (int) self::$company->id();

        $builder = $this->getFormBuilder();
        $builder->addInvoice($invoice);

        self::hasCard();

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);

        $parameters = [
            'payment_source_type' => self::$card->object,
            'payment_source_id' => self::$card->id(),
            'payment_flow' => $paymentFlow->identifier,
        ];

        // Call the method being tested

        $this->getFormProcessor()->handleSubmit($form, $parameters);
    }

    public function testHandleSubmit(): void
    {
        // Setup - Models, Mocks, etc.

        self::$company->accounts_receivable_settings->saved_cards_require_cvc = false;
        self::$company->accounts_receivable_settings->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $builder = $this->getFormBuilder();
        $builder->addInvoice($invoice);

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $this->getFormProcessor()->handleSubmit($form, $parameters);

        // Verify the results

        // should create a payment
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($invoice->id(), $transaction->invoice);
        $this->assertEquals(100, $transaction->amount);
    }

    public function testHandleSubmitMerchantAccountRouting(): void
    {
        // Setup - Models, Mocks, etc.

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $merchantAccount = new MerchantAccount();
        $merchantAccount->gateway = 'mock';
        $merchantAccount->gateway_id = 'user_test';
        $merchantAccount->name = 'Test Company';
        $merchantAccount->top_up_threshold_num_of_days = 14;
        $merchantAccount->credentials = (object) [];
        $merchantAccount->saveOrFail();

        $routing = new MerchantAccountRouting();
        $routing->invoice_id = (int) $invoice->id();
        $routing->method = PaymentMethod::CREDIT_CARD;
        $routing->merchant_account_id = (int) $merchantAccount->id();
        $routing->saveOrFail();

        $test = $this;

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class.','.OneTimeChargeInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('charge')
            ->andReturnUsing(function (Customer $customer, MerchantAccount $account, Money $amount) use ($test, $merchantAccount) {
                $this->assertEquals($merchantAccount->id(), $account->id());

                return $test->charge($customer, $amount);
            })
            ->once();

        $builder = $this->getFormBuilder();
        $processor = $this->getFormProcessorForGateway($gateway);
        $builder->setMethod(self::$paymentMethod);
        $builder->addInvoice($invoice);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $processor->handleSubmit($form, $parameters);

        // Verify the results

        $this->assertInstanceOf(Payment::class, $payment);
        $result = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals('mock', $result->gateway);
        $this->assertEquals(100, $result->amount);
        $this->assertEquals($invoice->id(), $result->invoice);
        $this->assertTrue($invoice->refresh()->paid);
    }

    public function testHandleSubmitFail(): void
    {
        // Setup - Models, Mocks, etc.

        $this->expectException(FormException::class);
        $this->expectExceptionMessage('error');

        $invoice = new Invoice(['id' => -6]);
        $invoice->number = '1';
        $invoice->currency = 'usd';
        $invoice->balance = 100;
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);

        $parameters = [];

        $e = new ChargeException('error');
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class.','.OneTimeChargeInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('charge')
            ->andThrow($e);

        $builder = $this->getFormBuilder();
        $processor = $this->getFormProcessorForGateway($gateway);
        $builder->addInvoice($invoice);
        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested

        $processor->handleSubmit($form, $parameters);
    }

    public function testHandleSubmitCustomAmount(): void
    {
        // Setup - Models, Mocks, etc.
        $settings = new PaymentFormSettings(
            self::$company,
            true,
            false,
            false,
            false
        );

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $builder = $this->getFormBuilder($settings);
        $builder->addInvoice($invoice, PaymentAmountOption::PayPartial, new Money('usd', 5000));

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $this->getFormProcessor()->handleSubmit($form, $parameters);

        // Verify the results

        $this->assertInstanceOf(Payment::class, $payment);
        $result = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals('mock', $result->gateway);
        $this->assertEquals(50, $result->amount);
        $this->assertEquals($invoice->id(), $result->invoice);
        $this->assertFalse($invoice->refresh()->paid);
    }

    public function testHandleSubmitEnrollAutoPay(): void
    {
        // Setup - Models, Mocks, etc.

        $customer = self::$customer;
        $customer->clearDefaultPaymentSource();

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->items = [['unit_cost' => 100]];
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class.','.OneTimeChargeInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('vaultSource')
            ->andReturnUsing([$this, 'vaultSource']);

        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: Money::fromDecimal('usd', 10000),
            gateway: 'mock',
            gatewayId: uniqid(),
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: null,
            description: '',
        );

        $gateway->shouldReceive('chargeSource')
            ->andReturn($charge)
            ->once();

        $builder = $this->getFormBuilder();
        $processor = $this->getFormProcessorForGateway($gateway);
        $builder->addInvoice($invoice);
        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = [
            'enroll_autopay' => true,
            'payment_flow' => $paymentFlow->identifier,
        ];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $processor->handleSubmit($form, $parameters);

        // Verify the results

        $this->assertInstanceOf(Payment::class, $payment);
        $result = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals('mock', $result->gateway);
        $this->assertEquals(100, $result->amount);
        $this->assertEquals($invoice->id(), $result->invoice);
        $this->assertTrue($invoice->refresh()->paid);

        $this->assertTrue($customer->refresh()->autopay);
        $this->assertInstanceOf(Card::class, $customer->payment_source);
    }

    public function testHandleSubmitEnrollAutoPaySavedSource(): void
    {
        // Setup - Models, Mocks, etc.

        $customer = self::$customer;

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->currency = 'usd';
        $invoice->items = [['unit_cost' => 100]];
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();

        $builder = $this->getFormBuilder();
        $builder->addInvoice($invoice);

        $builder->setMethod(self::$paymentMethod);
        $builder->setPaymentSource((string) $customer->default_source_type, (string) $customer->default_source_id);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = [
            'enroll_autopay' => true,
            'payment_flow' => $paymentFlow->identifier,
        ];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $this->getFormProcessor()->handleSubmit($form, $parameters);

        // Verify the results

        $this->assertInstanceOf(Payment::class, $payment);
        $result = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals('mock', $result->gateway);
        $this->assertEquals(100, $result->amount);
        $this->assertEquals($invoice->id(), $result->invoice);
        $this->assertTrue($invoice->refresh()->paid);

        $this->assertTrue($customer->refresh()->autopay);
        $this->assertInstanceOf(Card::class, $customer->payment_source);
    }

    public function testHandleSubmitMakeDefault(): void
    {
        // Setup - Models, Mocks, etc.

        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->items = [['unit_cost' => 100]];
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class.','.OneTimeChargeInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('vaultSource')
            ->andReturnUsing([$this, 'vaultSource']);

        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: Money::fromDecimal('usd', 10000),
            gateway: 'mock',
            gatewayId: uniqid(),
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: null,
            description: '',
        );

        $gateway->shouldReceive('chargeSource')
            ->andReturn($charge)
            ->once();
        $gateway->shouldReceive('deleteSource');

        $builder = $this->getFormBuilder();
        $processor = $this->getFormProcessorForGateway($gateway);
        $builder->addInvoice($invoice);
        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = [
            'make_default' => true,
            'payment_flow' => $paymentFlow->identifier,
        ];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $processor->handleSubmit($form, $parameters);

        // Verify the results

        $this->assertInstanceOf(Payment::class, $payment);
        $result = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals('mock', $result->gateway);
        $this->assertEquals(100, $result->amount);
        $this->assertEquals($invoice->id(), $result->invoice);
        $this->assertTrue($invoice->refresh()->paid);
    }

    public function testHandleSubmitUpdateEmail(): void
    {
        // Setup - Models, Mocks, etc.
        self::$customer->email = null;
        self::$customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->items = [['unit_cost' => 100]];
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->saveOrFail();

        $builder = $this->getFormBuilder();
        $builder->addInvoice($invoice);

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = [
            'email' => 'test@example.com',
            'payment_flow' => $paymentFlow->identifier,
        ];

        // Call the method being tested

        $this->getFormProcessor()->handleSubmit($form, $parameters);

        // Verify the results

        $this->assertEquals('test@example.com', self::$customer->refresh()->email);
    }

    public function testHandleSubmitMultipleInvoices(): void
    {
        // Setup - Models, Mocks, etc.

        self::$invoice->amount_paid = 0;
        self::$invoice->saveOrFail();
        self::$invoice2->amount_paid = 0;
        self::$invoice2->saveOrFail();

        $builder = $this->getFormBuilder();

        $invoices = [];
        for ($i = 0; $i < 5; ++$i) {
            $invoice = new Invoice();
            $invoice->setCustomer(self::$customer);
            $invoice->items = [['unit_cost' => 200]];
            $invoice->saveOrFail();
            $builder->addInvoice($invoice);
            $invoices[] = $invoice;
        }

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $this->getFormProcessor()->handleSubmit($form, $parameters);

        // Verify the results

        // should create a payment
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(1000, $transaction->paymentAmount()->toDecimal());
        foreach ($invoices as $invoice) {
            $this->assertTrue($invoice->paid);
        }
    }

    public function testHandleSubmitOfflinePayment(): void
    {
        $builder = $this->getFormBuilder();
        $builder->addInvoice(self::$invoice);
        $method = PaymentMethod::instance(self::$company, PaymentMethod::CHECK);
        $builder->setMethod($method);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);

        $parameters = [
            'date' => '2015-09-01',
            'method' => PaymentMethod::CHECK,
            'notes' => 'Thanks!',
            'payment_flow' => $paymentFlow->identifier,
        ];
        $expectedT = (int) mktime(18, 00, 00, 9, 1, 2015);
        $processor = $this->getFormProcessor();
        $this->assertNull($processor->handleSubmit($form, $parameters));

        $expectedDate = PromiseToPay::where('invoice_id', self::$invoice)->one();

        $this->assertEquals(PaymentMethod::CHECK, $expectedDate->method);
        $this->assertEquals($expectedT, $expectedDate->date);

        $n = Note::where('invoice_id', self::$invoice->id())
            ->where('notes', 'Thanks!')
            ->count();
        $this->assertEquals(1, $n);

        $method = PaymentMethod::instance(self::$company, PaymentMethod::OTHER);
        $builder->setMethod($method);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);
        $parameters['payment_flow'] = $paymentFlow->identifier;

        $this->assertNull($processor->handleSubmit($form, $parameters));
        $n = Note::where('invoice_id', self::$invoice->id())
            ->where('notes', 'Thanks!')
            ->count();
        $this->assertEquals(2, $n);
    }

    public function testHandleSubmitOfflinePaymentWithExistingNotes(): void
    {
        $builder = $this->getFormBuilder();
        $builder->addInvoice(self::$invoice);
        $method = PaymentMethod::instance(self::$company, PaymentMethod::CHECK);
        $builder->setMethod($method);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);

        $parameters = [
            'date' => '2015-09-02',
            'method' => PaymentMethod::WIRE_TRANSFER,
            'notes' => 'Second Round',
            'payment_flow' => $paymentFlow->identifier,
        ];
        $expectedT = (int) mktime(18, 0, 0, 9, 2, 2015);
        $processor = $this->getFormProcessor();
        $this->assertNull($processor->handleSubmit($form, $parameters));

        $expectedDate = PromiseToPay::where('invoice_id', self::$invoice)->one();

        $this->assertEquals(PaymentMethod::WIRE_TRANSFER, $expectedDate->method);
        $this->assertEquals($expectedT, $expectedDate->date);

        $n = Note::where('invoice_id', self::$invoice->id())
            ->where('notes', 'Second Round')
            ->count();
        $this->assertEquals(1, $n);
    }

    public function testHandleSubmitOfflinePaymentFail(): void
    {
        $this->expectException(FormException::class);
        $this->expectExceptionMessage('We could not validate the expected arrival date');

        $builder = $this->getFormBuilder();
        $builder->addInvoice(self::$invoice);
        $method = PaymentMethod::instance(self::$company, PaymentMethod::CHECK);
        $builder->setMethod($method);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);

        $parameters = [
            'date' => '2015-08-01',
            'payment_flow' => $paymentFlow->identifier,
        ];
        $this->getFormProcessor()->handleSubmit($form, $parameters);
    }

    public function testHandleSubmitEstimate(): void
    {
        // Setup - Models, Mocks, etc.

        self::$company->accounts_receivable_settings->saved_cards_require_cvc = false;
        self::$company->accounts_receivable_settings->saveOrFail();

        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);
        $estimate->items = [['unit_cost' => 500]];
        $estimate->deposit = 100;
        $estimate->saveOrFail();

        $builder = $this->getFormBuilder();
        $builder->addEstimate($estimate);

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();

        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $this->getFormProcessor()->handleSubmit($form, $parameters);

        // Verify the results

        // should create a payment
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(100, $payment->amount);
        $this->assertTrue($estimate->deposit_paid);
    }

    public function testHandleSubmitEstimateClosed(): void
    {
        // Setup - Models, Mocks, etc.

        self::$company->accounts_receivable_settings->saved_cards_require_cvc = false;
        self::$company->accounts_receivable_settings->saveOrFail();

        $estimate = new Estimate();
        $estimate->setCustomer(self::$customer);
        $estimate->items = [['unit_cost' => 500]];
        $estimate->deposit = 100;
        $estimate->closed = true;
        $estimate->saveOrFail();

        $builder = $this->getFormBuilder();
        $builder->addEstimate($estimate);

        $builder->setMethod(self::$paymentMethod);
        $form = $builder->build();
        $paymentFlow = $this->makePaymentFlow($form);
        $parameters = ['payment_flow' => $paymentFlow->identifier];

        // Call the method being tested

        /** @var Payment $payment */
        $payment = $this->getFormProcessor()->handleSubmit($form, $parameters);

        // Verify the results

        // should create a payment
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(100, $payment->amount);
        $this->assertTrue($estimate->deposit_paid);
    }

    public function testSetCustomAmountBeforeInvoice(): void
    {
        $builder = $this->getFormBuilder();
        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->currency = 'usd';
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->balance = 200;
        $builder->addInvoice($invoice, PaymentAmountOption::PayPartial, new Money('usd', 10000));

        $form = $builder->build();

        $this->assertEquals(10000, $form->totalAmount->amount);
    }

    public function charge(Customer $customer, Money $amount): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: 'mock',
            gatewayId: uniqid(),
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: null,
            description: '',
        );
    }

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): CardValueObject
    {
        return new CardValueObject(
            customer: $customer,
            gateway: 'mock',
            gatewayId: uniqid(),
            chargeable: true,
            brand: 'Visa',
            funding: 'unknown',
            last4: '1234',
            expMonth: 2,
            expYear: 2020,
        );
    }
}
