<?php

namespace App\Tests\PaymentProcessing\Operations;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\EntryPoint\QueueJob\AutoPayJob;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Exceptions\AutoPayException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Gateways\MockGateway;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\AutoPay;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\Tests\AppTestCase;
use Mockery;

class AutoPayTest extends AppTestCase
{
    private static Invoice $paidInvoice;
    private static Invoice $closedInvoice;
    private static Invoice $draftInvoice;
    private static Invoice $autoInvoice;
    private static Invoice $pendingInvoice;
    private static Invoice $paymentPlanInvoice;
    private static Transaction $pendingTxn;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::acceptsCreditCards();
        self::hasCustomer();
        self::hasCard(MockGateway::ID);
        self::hasBankAccount(MockGateway::ID);

        // set up a paid AutoPay invoice
        self::$paidInvoice = new Invoice();
        self::$paidInvoice->autopay = true;
        self::$paidInvoice->setCustomer(self::$customer);
        self::$paidInvoice->saveOrFail();

        // set up a closed AutoPay invoice
        self::$closedInvoice = new Invoice();
        self::$closedInvoice->autopay = true;
        self::$closedInvoice->setCustomer(self::$customer);
        self::$closedInvoice->items = [['unit_cost' => 100]];
        self::$closedInvoice->closed = true;
        self::$closedInvoice->saveOrFail();

        // set up a draft AutoPay invoice
        self::$draftInvoice = new Invoice();
        self::$draftInvoice->autopay = true;
        self::$draftInvoice->setCustomer(self::$customer);
        self::$draftInvoice->items = [['unit_cost' => 100]];
        self::$draftInvoice->draft = true;
        self::$draftInvoice->saveOrFail();

        // set up an AutoPay invoice
        self::$autoInvoice = new Invoice();
        self::$autoInvoice->autopay = true;
        self::$autoInvoice->setCustomer(self::$customer);
        self::$autoInvoice->items = [['unit_cost' => 100]];
        self::$autoInvoice->saveOrFail();

        // set up an AutoPay invoice with a pending txn
        self::$pendingInvoice = new Invoice();
        self::$pendingInvoice->autopay = true;
        self::$pendingInvoice->setCustomer(self::$customer);
        self::$pendingInvoice->items = [['unit_cost' => 100]];
        self::$pendingInvoice->saveOrFail();

        self::$pendingTxn = new Transaction();
        self::$pendingTxn->setCustomer(self::$customer);
        self::$pendingTxn->setInvoice(self::$pendingInvoice);
        self::$pendingTxn->status = Transaction::STATUS_PENDING;
        self::$pendingTxn->amount = 100;
        self::$pendingTxn->saveOrFail();

        self::$paymentPlanInvoice = self::createPaymentPlanInvoice();

        // set up a voided invoice
        $voidedInvoice = new Invoice();
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->autopay = true;
        $voidedInvoice->items = [['unit_cost' => 1000]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();
    }

    protected function tearDown(): void
    {
        self::getService('test.process_payment')->setGatewayFactory(self::getService('test.payment_gateway_factory'));
    }

    private static function createPaymentPlanInvoice(): Invoice
    {
        // set up an invoice with a payment plan
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = time();
        $installment1->amount = 25;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+1 month');
        $installment2->amount = 25;

        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+2 months');
        $installment3->amount = 50;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
            $installment3,
        ];

        $paymentPlanInvoice = new Invoice();
        $paymentPlanInvoice->setCustomer(self::$customer);
        $paymentPlanInvoice->items = [['unit_cost' => 100]];
        $paymentPlanInvoice->saveOrFail();
        $paymentPlanInvoice->attachPaymentPlan($paymentPlan, true, true);

        return $paymentPlanInvoice;
    }

    private function getAutoPay(PaymentGatewayInterface $gateway = null): AutoPay
    {
        $gateway = $gateway ?? new MockGateway();

        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')->andReturn($gateway);
        $processPayment = self::getService('test.process_payment');
        $processPayment->setGatewayFactory($gatewayFactory);

        $autoPay = self::getService('test.autopay');
        $autoPay->setProcessPayment($processPayment);

        return $autoPay;
    }

    public function testGetCollectionAmountNormalInvoice(): void
    {
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->balance = 100;

        $amount = $this->getAutoPay()->getCollectionAmount($invoice, AutoPay::PAYMENT_PLAN_MODE_CURRENTLY_DUE);

        $this->assertInstanceOf(Money::class, $amount);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(10000, $amount->amount);
    }

    public function testGetCollectionAmountPaymentPlanDueNowMode(): void
    {
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->balance = 100;

        $paymentPlan = new PaymentPlan();
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('-1 days');
        $installment1->amount = 200;
        $installment1->balance = 0;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = time();
        $installment2->amount = 300;
        $installment2->balance = 300;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = time();
        $installment3->amount = 400;
        $installment3->balance = 400;
        $installment4 = new PaymentPlanInstallment();
        $installment4->date = strtotime('+1 month');
        $installment4->amount = 500;
        $installment4->balance = 500;
        $paymentPlan->installments = [$installment1, $installment2, $installment3, $installment4];
        $invoice->payment_plan_id = -1;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $amount = $this->getAutoPay()->getCollectionAmount($invoice, AutoPay::PAYMENT_PLAN_MODE_CURRENTLY_DUE);

        $this->assertInstanceOf(Money::class, $amount);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(70000, $amount->amount);
    }

    public function testGetCollectionAmountPaymentPlanNextMode(): void
    {
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->balance = 100;

        $paymentPlan = new PaymentPlan();
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('-1 days');
        $installment1->amount = 200;
        $installment1->balance = 0;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('-1 month');
        $installment2->amount = 300;
        $installment2->balance = 300;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = time();
        $installment3->amount = 400;
        $installment3->balance = 400;
        $installment4 = new PaymentPlanInstallment();
        $installment4->date = strtotime('+1 month');
        $installment4->amount = 500;
        $installment4->balance = 500;
        $paymentPlan->installments = [$installment1, $installment2, $installment3, $installment4];
        $invoice->payment_plan_id = -1;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $amount = $this->getAutoPay()->getCollectionAmount($invoice, AutoPay::PAYMENT_PLAN_MODE_NEXT);

        $this->assertInstanceOf(Money::class, $amount);
        $this->assertEquals('usd', $amount->currency);
        $this->assertEquals(30000, $amount->amount);
    }

    public function testCollectInvalidCollectionMode(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Cannot collect on an invoice without AutoPay enabled.');

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectInvoiceClosed(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Cannot collect on closed invoices. Please reopen the invoice first.');

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $invoice->closed = true;

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectInvoiceVoided(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Cannot collect on voided invoices.');

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $invoice->voided = true;

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectInvoicePaid(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('This invoice has already been paid.');

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $invoice->paid = true;

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectInvoiceDraft(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Cannot collect on an invoice that has not been issued yet. Please issue the invoice first.');

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $invoice->draft = true;

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectInactivePaymentPlan(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Cannot collect while there is an inactive payment plan attached to this invoice.');

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->status = PaymentPlan::STATUS_PENDING_SIGNUP;
        $invoice->payment_plan_id = 10;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectNoPaymentSource(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Cannot collect on a customer without a payment source.');

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectUnverifiedPaymentSource(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Cannot collect on a customer until the payment source is verified.');

        self::$bankAccount->verified = false;
        self::$bankAccount->gateway = StripeGateway::ID;
        $this->assertTrue(self::$bankAccount->save());
        self::$customer->setDefaultPaymentSource(self::$bankAccount);

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectPendingPayments(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage("Payment cannot be processed because it's applied to an invoice with a pending payment");

        // set up customer payment source
        $this->assertTrue(self::$customer->setDefaultPaymentSource(self::$card));

        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $invoice->items = [['unit_cost' => 100]];
        $this->assertTrue($invoice->save());

        $payment = new Transaction();
        $payment->tenant_id = (int) self::$company->id();
        $payment->setInvoice($invoice);
        $payment->amount = 100;
        $payment->status = Transaction::STATUS_PENDING;
        $this->assertTrue($payment->save());

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectFailed(): void
    {
        // set up customer payment source
        $this->assertTrue(self::$customer->setDefaultPaymentSource(self::$card));

        // set up invoice
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'usd';
        $invoice->autopay = true;
        $time = time();
        $invoice->next_payment_attempt = $time;
        $invoice->items = [['unit_cost' => 100]];
        $invoice->balance = 100;
        $invoice->saveOrFail();

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount, $parameters, $description, $invoices) use ($invoice) {
                $this->assertEquals($invoice, $invoices[0]);
                $expected = new Money('usd', 10000);
                $this->assertEquals($expected, $amount);

                throw new ChargeException('Card declined.');
            });

        try {
            $this->getAutoPay($gateway)->collect($invoice);
        } catch (AutoPayException $e) {
        }

        $this->assertEquals('Card declined.', $e->getMessage()); /* @phpstan-ignore-line */

        // should reschedule a payment attempt according to the retry schedule
        $this->assertEquals(strtotime('+3 days', $time), $invoice->next_payment_attempt);
        $this->assertEquals(1, $invoice->attempt_count);

        // simulate a failed payment being created asynchronously
        $transaction = new Transaction();
        $transaction->setInvoice($invoice);
        $transaction->amount = $invoice->balance;
        $transaction->status = Transaction::STATUS_FAILED;
        $this->assertTrue($transaction->save());

        // the payment attempt schedule should be unchanged
        $this->assertEquals(1, $invoice->refresh()->attempt_count);
        $this->assertEquals(strtotime('+3 days', $time), $invoice->next_payment_attempt);

        $this->assertTrue($invoice->delete());
    }

    public function testCollectCurrencyNotSupported(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage("The stripe payment gateway / credit_card payment method does not support the 'dne' currency.");

        // set up customer payment source
        self::$card->gateway = 'stripe';
        $this->assertTrue(self::$customer->setDefaultPaymentSource(self::$card));

        // set up invoice
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->currency = 'dne'; // made up currency not supported by stripe
        $invoice->autopay = true;
        $invoice->balance = 100;
        $invoice->setRelation('customer', self::$customer);

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectAmountTooSmall(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Payment amount cannot be less than 0.01 USD');

        // set up customer payment source
        self::$card->gateway = MockGateway::ID;
        $this->assertTrue(self::$customer->setDefaultPaymentSource(self::$card));

        // set up invoice
        $invoice = new Invoice();
        $invoice->tenant_id = (int) self::$company->id();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $invoice->currency = 'usd';
        $invoice->balance = 0;

        $this->getAutoPay()->collect($invoice);
    }

    public function testCollectLocked(): void
    {
        $this->expectException(AutoPayException::class);
        $this->expectExceptionMessage('Duplicate payment attempt detected.');

        // lock the invoice
        self::getService('test.redis')->setnx('invoiced.localhost:payment_lock.invoice.'.self::$autoInvoice->id(), 60);

        // set up customer payment source
        $this->assertTrue(self::$autoInvoice->customer()->setDefaultPaymentSource(self::$card));

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount, $parameters, $description, $invoices) {
                $this->assertEquals(self::$autoInvoice, $invoices[0]);
                $expected = new Money('usd', 10000);
                $this->assertEquals($expected, $amount);

                return new ChargeValueObject(
                    customer: self::$customer,
                    amount: $amount,
                    gateway: MockGateway::ID,
                    gatewayId: uniqid(),
                    method: PaymentMethod::CREDIT_CARD,
                    status: Charge::SUCCEEDED,
                    merchantAccount: null,
                    source: null,
                    description: '',
                );
            });

        $this->getAutoPay($gateway)->collect(self::$autoInvoice);
    }

    public function testCollect(): void
    {
        // clear any locks
        self::getService('test.redis')->flushdb();

        // set up customer payment source
        $this->assertTrue(self::$autoInvoice->customer()->setDefaultPaymentSource(self::$card));

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount, $parameters, $description, $invoices) {
                $this->assertEquals(self::$autoInvoice, $invoices[0]);
                $expected = new Money('usd', 10000);
                $this->assertEquals($expected, $amount);

                return new ChargeValueObject(
                    customer: self::$customer,
                    amount: $amount,
                    gateway: MockGateway::ID,
                    gatewayId: uniqid(),
                    method: PaymentMethod::CREDIT_CARD,
                    status: Charge::SUCCEEDED,
                    merchantAccount: null,
                    source: null,
                    description: '',
                );
            });

        $this->getAutoPay($gateway)->collect(self::$autoInvoice);

        $this->assertNull(self::$autoInvoice->next_payment_attempt);
        $this->assertEquals(1, self::$autoInvoice->attempt_count);
    }

    public function testCollectPaymentPlanFailed(): void
    {
        // make payment plan active
        self::getService('test.approve_payment_plan')->approve(self::$paymentPlanInvoice->paymentPlan(), '127.0.0.1', 'Invoiced/Test');

        // set up customer payment source
        $this->assertTrue(self::$paymentPlanInvoice->customer()->setDefaultPaymentSource(self::$card));

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andThrow(new ChargeException('Card declined.'));

        self::$paymentPlanInvoice->refresh();

        try {
            $this->getAutoPay($gateway)->collect(self::$paymentPlanInvoice);
        } catch (AutoPayException $e) {
        }

        // should reschedule a payment attempt according to the retry schedule
        $installments = self::$paymentPlanInvoice->paymentPlan()->installments; /* @phpstan-ignore-line */
        $this->assertEquals(1, self::$paymentPlanInvoice->attempt_count);
        $this->assertBetween((int) self::$paymentPlanInvoice->next_payment_attempt, strtotime('+3 days') - 5, strtotime('+3 days') + 5);

        // simulate a failed payment being created asynchronously
        $transaction = new Transaction();
        $transaction->setInvoice(self::$paymentPlanInvoice);
        $transaction->amount = $installments[0]->balance;
        $transaction->status = Transaction::STATUS_FAILED;
        $this->assertTrue($transaction->save());

        // the payment attempt schedule should be unchanged
        $this->assertEquals(1, self::$paymentPlanInvoice->refresh()->attempt_count);
        $this->assertEquals(strtotime('+3 days', $installments[0]->date), self::$paymentPlanInvoice->next_payment_attempt);
    }

    public function testAutoPayFromPast(): void
    {
        $paymentPlanInvoice = self::createPaymentPlanInvoice();
        self::getService('test.approve_payment_plan')->approve($paymentPlanInvoice->paymentPlan(), '127.0.0.1', 'Invoiced/Test');
        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andThrow(new ChargeException('Card declined.'));

        $paymentPlanInvoice->attempt_count = 0;
        $paymentPlanInvoice->next_payment_attempt = strtotime('-49 hours');
        $paymentPlanInvoice->saveOrFail();
        try {
            $this->getAutoPay($gateway)->collect($paymentPlanInvoice);
        } catch (AutoPayException $e) {
        }
        $this->assertEquals(1, $paymentPlanInvoice->refresh()->attempt_count);
        $this->assertBetween((int) $paymentPlanInvoice->next_payment_attempt, strtotime('+1 days') - 5, strtotime('+1 days') + 5);
    }

    public function testCollectPendingPayment(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $invoice->items = [['unit_cost' => 500]];
        $invoice->saveOrFail();

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount) {
                $expected = new Money('usd', 50000);
                $this->assertEquals($expected, $amount);

                return new ChargeValueObject(
                    customer: self::$customer,
                    amount: $amount,
                    gateway: MockGateway::ID,
                    gatewayId: uniqid(),
                    method: PaymentMethod::CREDIT_CARD,
                    status: Charge::PENDING,
                    merchantAccount: null,
                    source: null,
                    description: '',
                );
            });

        $this->getAutoPay($gateway)->collect($invoice);

        // should not schedule another collection attempt and should increment the attempt count
        // !important, should not increase attempt count, since this value is increased
        // during transaction from pending to failed
        $this->assertNull($invoice->next_payment_attempt);
        $this->assertEquals(0, $invoice->attempt_count);
        $this->assertEquals(InvoiceStatus::Pending->value, $invoice->status);
    }

    public function testCollectPaymentPlan(): void
    {
        // make payment plan active
        self::getService('test.approve_payment_plan')->approve(self::$paymentPlanInvoice->paymentPlan(), '127.0.0.1', 'Invoiced/Test');

        // set up customer payment source
        $this->assertTrue(self::$paymentPlanInvoice->customer()->setDefaultPaymentSource(self::$card));

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount, $parameters, $description, $invoices) {
                $this->assertEquals(self::$paymentPlanInvoice, $invoices[0]);
                $expected = new Money('usd', 2500);
                $this->assertEquals($expected, $amount);

                return new ChargeValueObject(
                    customer: self::$customer,
                    amount: $amount,
                    gateway: MockGateway::ID,
                    gatewayId: uniqid(),
                    method: PaymentMethod::CREDIT_CARD,
                    status: Charge::SUCCEEDED,
                    merchantAccount: null,
                    source: null,
                    description: '',
                );
            });

        $this->getAutoPay($gateway)->collect(self::$paymentPlanInvoice);

        // should schedule a collection attempt as the date of the next installment date
        $installments = self::$paymentPlanInvoice->paymentPlan()->installments; /* @phpstan-ignore-line */
        $this->assertEquals(0, $installments[0]->balance);
        $this->assertEquals(self::$paymentPlanInvoice->next_payment_attempt, $installments[1]->date);
        $this->assertEquals(0, self::$paymentPlanInvoice->attempt_count);
    }

    /**
     * @depends testCollectPaymentPlan
     */
    public function testCollectPaymentPlanFinish(): void
    {
        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount, $parameters, $description, $invoices) {
                $this->assertEquals(self::$paymentPlanInvoice, $invoices[0]);

                return new ChargeValueObject(
                    customer: self::$customer,
                    amount: $amount,
                    gateway: MockGateway::ID,
                    gatewayId: uniqid(),
                    method: PaymentMethod::CREDIT_CARD,
                    status: Charge::SUCCEEDED,
                    merchantAccount: null,
                    source: null,
                    description: '',
                );
            });

        // collect the final 2 installments
        $this->getAutoPay($gateway)->collect(self::$paymentPlanInvoice, AutoPay::PAYMENT_PLAN_MODE_NEXT);
        $this->getAutoPay($gateway)->collect(self::$paymentPlanInvoice, AutoPay::PAYMENT_PLAN_MODE_NEXT);

        // the invoice should be paid and there should be no more collection attempts
        $this->assertTrue(self::$paymentPlanInvoice->paid);
        $this->assertNull(self::$paymentPlanInvoice->next_payment_attempt);
        $this->assertEquals(1, self::$paymentPlanInvoice->attempt_count);

        // verify the payment plan is finished
        $this->assertEquals(PaymentPlan::STATUS_FINISHED, self::$paymentPlanInvoice->paymentPlan()->status); /* @phpstan-ignore-line */

        // verify the installment balances
        $installments = self::$paymentPlanInvoice->paymentPlan()->installments; /* @phpstan-ignore-line */
        $this->assertEquals(0, $installments[0]->balance);
        $this->assertEquals(0, $installments[1]->balance);
        $this->assertEquals(0, $installments[2]->balance);
    }

    public function testCollectWithPaymentSource(): void
    {
        // clear any locks
        self::getService('test.redis')->flushdb();

        // reopen the invoice and set a payment source
        self::$autoInvoice->attempt_count = 0;
        self::$autoInvoice->amount_paid = 0;
        self::$autoInvoice->closed = false;
        self::$autoInvoice->setPaymentSource(self::$card);
        $this->assertTrue(self::$autoInvoice->save());

        // clear the customer's payment source
        // this test should use the payment source attached to the invoice
        $this->assertTrue(self::$autoInvoice->customer()->clearDefaultPaymentSource());

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount, $parameters, $description, $invoices) {
                $this->assertEquals(self::$autoInvoice, $invoices[0]);
                $expected = new Money('usd', 10000);
                $this->assertEquals($expected, $amount);
                $this->assertInstanceOf(Card::class, $source);
                $this->assertEquals(self::$card->id(), $source->id());

                return new ChargeValueObject(
                    customer: self::$customer,
                    amount: $amount,
                    gateway: MockGateway::ID,
                    gatewayId: uniqid(),
                    method: PaymentMethod::CREDIT_CARD,
                    status: Charge::SUCCEEDED,
                    merchantAccount: null,
                    source: null,
                    description: '',
                );
            });

        $this->getAutoPay($gateway)->collect(self::$autoInvoice);

        $this->assertNull(self::$autoInvoice->next_payment_attempt);
        $this->assertEquals(1, self::$autoInvoice->attempt_count);
    }

    public function testCollectStaleInvoice(): void
    {
        // this will reset the next payment attempt to the future
        self::$autoInvoice->next_payment_attempt = strtotime('+1 hour');
        self::$autoInvoice->attempt_count = 0;
        self::$autoInvoice->amount_paid = 0;
        self::$autoInvoice->closed = false;
        self::$autoInvoice->clearPaymentSource();
        self::$autoInvoice->saveOrFail();

        $this->assertTrue(self::$autoInvoice->customer()->setDefaultPaymentSource(self::$card));

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount) {
                return new ChargeValueObject(
                    customer: self::$customer,
                    amount: $amount,
                    gateway: MockGateway::ID,
                    gatewayId: uniqid(),
                    method: PaymentMethod::CREDIT_CARD,
                    status: Charge::SUCCEEDED,
                    merchantAccount: null,
                    source: null,
                    description: '',
                );
            });

        $invoice1 = self::$autoInvoice;
        $invoice2 = Invoice::findOrFail(self::$autoInvoice->id());

        $this->getAutoPay($gateway)->collect($invoice1);

        $duplicateAttemptFailed = false;

        try {
            $this->getAutoPay()->collect($invoice2);
        } catch (AutoPayException $e) {
            $duplicateAttemptFailed = true;
        }

        $this->assertTrue($duplicateAttemptFailed, 'Duplicate collection attempt should be blocked given stale invoice that has already been collected.');
    }

    /**
     * @depends testCollect
     */
    public function testPerformScheduledCollections(): void
    {
        // this will reset the next payment attempt to the future
        self::$autoInvoice->next_payment_attempt = strtotime('+1 hour');
        self::$autoInvoice->attempt_count = 0;
        self::$autoInvoice->amount_paid = 0;
        self::$autoInvoice->closed = false;
        self::$autoInvoice->clearPaymentSource();
        self::$autoInvoice->saveOrFail();

        $this->assertTrue(self::$autoInvoice->customer()->setDefaultPaymentSource(self::$card));

        // set up payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.PaymentSourceVaultInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getId')
            ->andReturn(MockGateway::ID);
        $gateway->shouldReceive('chargeSource')
            ->andReturnUsing(function ($source, $amount) {
                return new ChargeValueObject(
                    customer: self::$customer,
                    amount: $amount,
                    gateway: MockGateway::ID,
                    gatewayId: uniqid(),
                    method: PaymentMethod::CREDIT_CARD,
                    status: Charge::SUCCEEDED,
                    merchantAccount: null,
                    source: null,
                    description: '',
                );
            });

        // NOTE this could break if there were other invoices
        // outside of the tests that were scheduled to be collected
        $autopay = new AutoPayJob($this->getAutoPay($gateway), self::getService('test.lock_factory'));
        $autopay->args = ['tenant_id' => self::$company->id];
        $autopay->perform();
        // we should not proceed invoices with future payment attempt date
        $this->assertEquals(0, self::$autoInvoice->refresh()->attempt_count);
        $this->assertNotNull(self::$autoInvoice->paid = false);

        self::getService('test.tenant')->set(self::$company);

        // set the next payment attempts into the past
        self::$autoInvoice->next_payment_attempt = strtotime('-1 hour');
        $this->assertTrue(self::$autoInvoice->save());

        self::$pendingInvoice->next_payment_attempt = strtotime('-1 hour');
        $this->assertTrue(self::$pendingInvoice->save());

        // the first invoice should be collected but not the
        // pending invoice
        $autopay = new AutoPayJob($this->getAutoPay($gateway), self::getService('test.lock_factory'));
        $autopay->args = ['tenant_id' => self::$company->id()];
        $autopay->perform();

        self::getService('test.tenant')->set(self::$company);

        // should only collect on the open invoice
        $this->assertEquals(1, self::$autoInvoice->refresh()->attempt_count);
        $this->assertNull(self::$autoInvoice->next_payment_attempt);

        // should not touch these invoices
        foreach ([self::$closedInvoice, self::$paidInvoice, self::$draftInvoice, self::$pendingInvoice] as $invoice) {
            $this->assertEquals(0, $invoice->refresh()->attempt_count, "Invoice # {$invoice->number} should not have any payment attempts");
        }

        $this->assertEquals(1, self::$paymentPlanInvoice->refresh()->attempt_count);
    }
}
