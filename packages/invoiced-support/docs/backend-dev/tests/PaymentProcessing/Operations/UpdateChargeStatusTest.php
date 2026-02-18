<?php

namespace App\Tests\PaymentProcessing\Operations;

use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\Utils\RandomString;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use App\Tests\AppTestCase;
use Mockery;

class UpdateChargeStatusTest extends AppTestCase
{
    const ORIGINAL_STATUS_CHECK = 123456;

    private static Invoice $invoice2;
    private static Invoice $invoice3;
    private static PaymentFlow $paymentFlow1;
    private static PaymentFlow $paymentFlow2;
    private static Charge $charge1;
    private static Charge $charge2;
    private static Charge $charge3;
    private static Charge $charge4;
    private static Charge $charge5;
    private static Charge $charge6;
    private static Payment $payment1;
    private static Payment $payment2;
    private static Payment $payment3;
    private static Payment $payment4;
    private static Payment $payment5;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasCard();
        self::acceptsCreditCards(TestGateway::ID);

        self::$paymentFlow1 = new PaymentFlow();
        self::$paymentFlow1->identifier = RandomString::generate();
        self::$paymentFlow1->status = PaymentFlowStatus::CollectPaymentDetails;
        self::$paymentFlow1->initiated_from = PaymentFlowSource::Api;
        self::$paymentFlow1->currency = 'usd';
        self::$paymentFlow1->amount = self::$invoice->balance;
        self::$paymentFlow1->saveOrFail();
        self::$charge1 = new Charge();
        self::$charge1->customer = self::$customer;
        self::$charge1->currency = 'usd';
        self::$charge1->amount = self::$invoice->balance;
        self::$charge1->status = Charge::PENDING;
        self::$charge1->gateway = TestGateway::ID;
        self::$charge1->gateway_id = 'ch_test';
        self::$charge1->last_status_check = self::ORIGINAL_STATUS_CHECK;
        self::$charge1->setPaymentSource(self::$card);
        self::$charge1->payment_flow = self::$paymentFlow1;
        self::$charge1->saveOrFail();
        self::$payment1 = self::addPayment(self::$charge1, self::$invoice);

        self::$paymentFlow2 = new PaymentFlow();
        self::$paymentFlow2->identifier = RandomString::generate();
        self::$paymentFlow2->status = PaymentFlowStatus::CollectPaymentDetails;
        self::$paymentFlow2->initiated_from = PaymentFlowSource::Api;
        self::$paymentFlow2->currency = 'usd';
        self::$paymentFlow2->amount = 500;
        self::$paymentFlow2->saveOrFail();
        self::$charge2 = new Charge();
        self::$charge2->customer = self::$customer;
        self::$charge2->currency = 'usd';
        self::$charge2->amount = 500;
        self::$charge2->status = Charge::PENDING;
        self::$charge2->gateway = TestGateway::ID;
        self::$charge2->gateway_id = 'ch_test2';
        self::$charge2->last_status_check = self::ORIGINAL_STATUS_CHECK;
        self::$charge2->setPaymentSource(self::$card);
        self::$charge2->payment_flow = self::$paymentFlow2;
        self::$charge2->saveOrFail();

        self::$invoice2 = new Invoice();
        self::$invoice2->setCustomer(self::$customer);
        self::$invoice2->items = [['unit_cost' => 200]];
        self::$invoice2->saveOrFail();

        self::$charge3 = new Charge();
        self::$charge3->customer = self::$customer;
        self::$charge3->currency = 'usd';
        self::$charge3->amount = 500;
        self::$charge3->status = Charge::PENDING;
        self::$charge3->gateway = TestGateway::ID;
        self::$charge3->gateway_id = 'ch_test3';
        self::$charge3->last_status_check = self::ORIGINAL_STATUS_CHECK;
        self::$charge3->setPaymentSource(self::$card);
        self::$charge3->saveOrFail();
        self::$payment2 = self::addPayment(self::$charge3, self::$invoice2);

        self::$invoice3 = new Invoice();
        self::$invoice3->setCustomer(self::$customer);
        self::$invoice3->items = [['unit_cost' => 201]];
        self::$invoice3->saveOrFail();

        self::$charge4 = new Charge();
        self::$charge4->customer = self::$customer;
        self::$charge4->currency = 'usd';
        self::$charge4->amount = 200;
        self::$charge4->status = Charge::PENDING;
        self::$charge4->gateway = TestGateway::ID;
        self::$charge4->gateway_id = 'ch_test4';
        self::$charge4->setPaymentSource(self::$card);
        self::$charge4->last_status_check = self::ORIGINAL_STATUS_CHECK;
        self::$charge4->saveOrFail();
        self::$charge4->created_at = strtotime('-31 days');
        self::$charge4->saveOrFail();
        self::$payment3 = self::addPayment(self::$charge4, self::$invoice3);

        self::$charge5 = new Charge();
        self::$charge5->customer = self::$customer;
        self::$charge5->currency = 'usd';
        self::$charge5->amount = 1;
        self::$charge5->status = Charge::PENDING;
        self::$charge5->gateway = TestGateway::ID;
        self::$charge5->gateway_id = 'ch_test5';
        self::$charge5->setPaymentSource(self::$card);
        self::$charge5->last_status_check = self::ORIGINAL_STATUS_CHECK;
        self::$charge5->saveOrFail();
        self::$payment4 = self::addPayment(self::$charge5, self::$invoice3);

        self::$charge6 = new Charge();
        self::$charge6->customer = self::$customer;
        self::$charge6->currency = 'usd';
        self::$charge6->amount = 1;
        self::$charge6->status = Charge::PENDING;
        self::$charge6->gateway = TestGateway::ID;
        self::$charge6->gateway_id = 'ch_test6';
        self::$charge6->setPaymentSource(self::$card);
        self::$charge6->last_status_check = self::ORIGINAL_STATUS_CHECK;
        self::$charge6->saveOrFail();
        self::$payment5 = self::addPayment(self::$charge6, self::$invoice3);
    }

    public function setUp(): void
    {
        parent::setUp();
        EventSpool::enable();
    }

    private static function addPayment(Charge $charge, Invoice $invoice): Payment
    {
        $payment = new Payment();
        $payment->setCustomer($charge->customer); /* @phpstan-ignore-line */
        $payment->charge = $charge;
        $payment->currency = $charge->currency;
        $payment->amount = $charge->amount;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'invoice' => $invoice,
                'amount' => $charge->amount,
            ],
        ];
        $payment->saveOrFail();

        $charge->payment = $payment;
        $charge->saveOrFail();

        return $payment;
    }

    private function getGatewayFactory(string $status, ?string $message = null): PaymentGatewayFactory
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.TransactionStatusInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getTransactionStatus')
            ->andReturn([$status, $message]);

        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')
            ->andReturn($gateway);

        return $gatewayFactory;
    }

    private function getChargeStatus(string $status, ?string $message = null): UpdateChargeStatus
    {
        $gatewayFactory = $this->getGatewayFactory($status, $message);
        $operation = self::getService('test.update_charge_status');
        $operation->setGatewayFactory($gatewayFactory);

        return $operation;
    }

    public function testUpdateSucceeded(): void
    {
        $updateChargeStatus = $this->getChargeStatus(Charge::SUCCEEDED);
        $updateChargeStatus->update(self::$charge1);

        $this->assertEquals(Charge::SUCCEEDED, self::$charge1->refresh()->status);
        $this->assertEquals(self::ORIGINAL_STATUS_CHECK, self::$charge1->last_status_check);
        $transaction = self::$payment1->getTransactions()[0];
        $this->assertEquals(Transaction::STATUS_SUCCEEDED, $transaction->status);
        $this->assertEquals(PaymentFlowStatus::Succeeded, self::$paymentFlow1->refresh()->status);
        $this->assertHasEvent(self::$charge1, EventType::ChargeSucceeded);
    }

    public function testUpdateFailed(): void
    {
        $updateChargeStatus = $this->getChargeStatus(Charge::FAILED);
        $updateChargeStatus->update(self::$charge2);

        $this->assertEquals(Charge::FAILED, self::$charge2->refresh()->status);
        $this->assertNull(self::$charge2->failure_message);
        $this->assertEquals(self::ORIGINAL_STATUS_CHECK, self::$charge2->last_status_check);
        $this->assertHasEvent(self::$charge2, EventType::ChargeFailed);
        $this->assertEquals(PaymentFlowStatus::Failed, self::$paymentFlow2->refresh()->status);
    }

    public function testUpdateOverpayment(): void
    {
        $updateChargeStatus = $this->getChargeStatus(Charge::SUCCEEDED);
        $updateChargeStatus->update(self::$charge3);

        $this->assertEquals(Charge::SUCCEEDED, self::$charge3->refresh()->status);
        $this->assertEquals(self::ORIGINAL_STATUS_CHECK, self::$charge3->last_status_check);
        $transaction = self::$payment2->getTransactions()[0];
        $this->assertEquals(Transaction::STATUS_SUCCEEDED, $transaction->status);
        $this->assertHasEvent(self::$charge3, EventType::ChargeSucceeded);
    }

    public function testUpdatePendingTooLong(): void
    {
        $updateChargeStatus = $this->getChargeStatus(Charge::PENDING);
        $updateChargeStatus->update(self::$charge4);

        $this->assertEquals(Charge::SUCCEEDED, self::$charge4->refresh()->status);
        $this->assertEquals(self::ORIGINAL_STATUS_CHECK, self::$charge4->last_status_check);
        $transaction = self::$payment3->getTransactions()[0];
        $this->assertEquals(Transaction::STATUS_SUCCEEDED, $transaction->status);
        $this->assertHasEvent(self::$charge4, EventType::ChargeSucceeded);
    }

    public function testUpdateStillPending(): void
    {
        $updateChargeStatus = $this->getChargeStatus(Charge::PENDING);
        $updateChargeStatus->update(self::$charge5);

        $this->assertEquals(Charge::PENDING, self::$charge5->refresh()->status);
        $this->assertNull(self::$charge5->failure_message);
        $this->assertGreaterThan(self::ORIGINAL_STATUS_CHECK, self::$charge5->last_status_check);
        $transaction = self::$payment4->getTransactions()[0];
        $this->assertEquals(Transaction::STATUS_PENDING, $transaction->status);
    }

    public function testInvd2406(): void
    {
        // Void the payment before attempting to update charge status
        self::$payment5->void();

        $updateChargeStatus = $this->getChargeStatus(Charge::FAILED);
        $updateChargeStatus->update(self::$charge6);

        $this->assertEquals(Charge::FAILED, self::$charge6->refresh()->status);
        $this->assertNull(self::$charge6->failure_message);
        $this->assertTrue(self::$payment5->voided);
        $this->assertEquals(self::ORIGINAL_STATUS_CHECK, self::$charge6->last_status_check);
        $this->assertHasEvent(self::$charge6, EventType::ChargeFailed);
    }

    public function testSucceededToFailed(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->autopay = true;
        $invoice->attempt_count = 1;
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = 100;
        $charge->status = Charge::PENDING;
        $charge->gateway = TestGateway::ID;
        $charge->gateway_id = 'ch_test_invd_3102';
        $charge->setPaymentSource(self::$card);
        $payment = self::addPayment($charge, $invoice);

        // Take the charge from Pending -> Succeeded
        $updateChargeStatus = $this->getChargeStatus(Charge::SUCCEEDED);
        $updateChargeStatus->update($charge);

        // Invoice should be paid
        $this->assertTrue($invoice->refresh()->paid);
        $this->assertEquals(0, $invoice->balance);
        $this->assertNull($invoice->next_payment_attempt);

        // Test the charge going from Succeeded -> Failed
        $updateChargeStatus = $this->getChargeStatus(Charge::FAILED);
        $updateChargeStatus->update($charge);

        // Invoice should no longer be paid
        $this->assertEquals('past_due', $invoice->refresh()->status);
        $this->assertFalse($invoice->closed);
        $this->assertFalse($invoice->paid);
        $this->assertNotNull($invoice->next_payment_attempt);
        $this->assertEquals(2, $invoice->attempt_count);
        $this->assertEquals(100, $invoice->balance);
        $this->assertTrue($payment->refresh()->voided);

        $this->assertHasEvent($charge, EventType::ChargeFailed);
    }
}
