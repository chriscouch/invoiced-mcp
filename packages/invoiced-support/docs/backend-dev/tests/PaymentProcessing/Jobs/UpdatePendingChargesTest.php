<?php

namespace App\Tests\PaymentProcessing\Jobs;

use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Statsd\StatsdClient;
use App\EntryPoint\CronJob\UpdatePendingCharges;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\ActivityLog\Models\Event;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Models\Charge;
use App\Tests\AppTestCase;
use Mockery;

class UpdatePendingChargesTest extends AppTestCase
{
    private static Invoice $invoice3;
    private static Invoice $invoice6;
    private static Invoice $invoice7;
    private static Invoice $invoice8;
    private static Charge $charge1;
    private static Charge $charge2;
    private static Charge $charge3;
    private static Charge $charge4;
    private static Charge $charge5;
    private static Charge $charge6;
    private static Charge $charge7;
    private static Charge $charge8;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::acceptsCreditCards(TestGateway::ID);
        self::hasCard();

        [self::$charge1] = self::createCharge('ch_test', 200, time());
        [self::$charge2] = self::createCharge('ch_test2', 500, time());
        [self::$charge3, self::$invoice3] = self::createCharge('ch_test3', 500, time());
        [self::$charge4] = self::createCharge('ch_test4', 200, strtotime('-31 day'));

        // from pending to success
        [self::$charge5] = self::createCharge('ch_test5', 200, time(), 0, true);
        // from pending to error
        [self::$charge6, self::$invoice6] = self::createCharge('ch_test6', 200, time(), 1, true);
        // from pending to error first attempt
        // INVD-793 fix
        [self::$charge7, self::$invoice7] = self::createCharge('ch_test7', 200, time(), 0, true);
        // from pending to error with payment plan
        // INVD-793 fix
        [self::$charge8, self::$invoice8] = self::createCharge('ch_test8', 200, time(), 0, true);
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = time();
        $installment1->amount = 100;
        $installment2 = new PaymentPlanInstallment();

        $installment2->date = strtotime('+1 month');
        $installment2->amount = 100;
        $chargePlan = new PaymentPlan();
        $chargePlan->installments = [
            $installment1,
            $installment2,
        ];
        self::$invoice8->attachPaymentPlan($chargePlan, true, true);
        self::getService('test.approve_payment_plan')->approve($chargePlan, '127.0.0.1', 'Firefox');
    }

    private static function createCharge(string $gatewayId, float $amount, int $date, int $attemptCount = 0, bool $autopay = true): array
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 200]];
        $invoice->notes = $gatewayId;
        $invoice->attempt_count = $attemptCount;
        $invoice->autopay = $autopay;
        $invoice->saveOrFail();

        $charge = new Charge();
        $charge->customer = self::$customer;
        $charge->currency = 'usd';
        $charge->amount = $amount;
        $charge->status = Charge::PENDING;
        $charge->gateway = TestGateway::ID;
        $charge->gateway_id = $gatewayId;
        $charge->setPaymentSource(self::$card);
        $charge->last_status_check = 0;
        $charge->saveOrFail();

        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->currency = 'usd';
        $payment->amount = $amount;
        $payment->applied_to = [
            [
                'type' => PaymentItemType::Invoice->value,
                'amount' => $amount,
                'invoice' => $invoice,
            ],
        ];
        $payment->charge = $charge;
        $payment->saveOrFail();

        $charge->created_at = $date;
        $charge->payment = $payment;
        $charge->saveOrFail();

        return [$charge, $invoice];
    }

    private function getJob(?PaymentGatewayFactory $gatewayFactory = null): UpdatePendingCharges
    {
        $gatewayFactory = $gatewayFactory ?? self::getService('test.payment_gateway_factory');
        $updateChargeStatus = self::getService('test.update_charge_status');
        $updateChargeStatus->setGatewayFactory($gatewayFactory);
        $job = new UpdatePendingCharges(self::getService('test.tenant'), $updateChargeStatus);
        $job->setStatsd(new StatsdClient());

        return $job;
    }

    public function testGetPendingCharges(): void
    {
        $job = $this->getJob();
        $charges = $job->getPendingCharges()->all();

        $this->assertCount(8, $charges);
        $this->assertEquals(self::$charge1->id(), $charges[0]->id());
        $this->assertEquals(self::$charge2->id(), $charges[1]->id());
        $this->assertEquals(self::$charge3->id(), $charges[2]->id());
        $this->assertEquals(self::$charge4->id(), $charges[3]->id());
        $this->assertEquals(self::$charge5->id(), $charges[4]->id());
        $this->assertEquals(self::$charge6->id(), $charges[5]->id());
        $this->assertEquals(self::$charge7->id(), $charges[6]->id());
        $this->assertEquals(self::$charge8->id(), $charges[7]->id());
    }

    public function testRun(): void
    {
        EventSpool::enable();

        $gateway = Mockery::mock(PaymentGatewayInterface::class.','.TransactionStatusInterface::class);
        $gateway->shouldReceive('validateConfiguration');
        $gateway->shouldReceive('getTransactionStatus')
            ->andReturnUsing(function ($chargeSource, $charge) {
                switch ($charge->gateway_id) {
                    case self::$charge2->gateway_id:
                    case self::$charge6->gateway_id:
                    case self::$charge7->gateway_id:
                    case self::$charge8->gateway_id:
                        return [Charge::FAILED, null];
                    case self::$charge4->gateway_id:
                        return [Charge::PENDING, null];
                }

                return [Charge::SUCCEEDED, null];
            });

        $gatewayFactory = Mockery::mock(PaymentGatewayFactory::class);
        $gatewayFactory->shouldReceive('get')
            ->andReturn($gateway);

        $job = $this->getJob($gatewayFactory);
        $job->execute(new Run());
        $this->assertEquals(8, $job->getTaskCount());

        $this->assertEquals(Charge::SUCCEEDED, self::$charge1->refresh()->status);

        $this->assertEquals(Charge::FAILED, self::$charge2->refresh()->status);
        $this->assertNull(self::$charge2->failure_message);
        $this->assertCreatedFailedEvent(self::$charge2);

        $this->assertEquals(Charge::SUCCEEDED, self::$charge3->refresh()->status);
        $this->assertNull(self::$invoice3->next_payment_attempt, 'Non AutoPay invoice should not have next payment day');

        $this->assertEquals(Charge::SUCCEEDED, self::$charge4->refresh()->status);

        $this->assertEquals(Charge::SUCCEEDED, self::$charge5->refresh()->status);

        $this->assertEquals(Charge::FAILED, self::$charge6->refresh()->status);
        $delta = strtotime('+5 days') - self::$invoice6->refresh()->next_payment_attempt;
        $this->assertBetween($delta, 0, 3, 'The next payment date should be 5 days ahead');
        $this->assertNull(self::$charge6->failure_message);
        $this->assertCreatedFailedEvent(self::$charge6);

        $this->assertEquals(Charge::FAILED, self::$charge7->refresh()->status);
        // one hour is added as invoice payment date is created one hour ahead
        $delta = strtotime('+3 days') - self::$invoice7->refresh()->next_payment_attempt;
        $this->assertBetween($delta, 0, 3, 'The next payment date should be 3 days ahead');
        $this->assertNull(self::$charge7->failure_message);
        $this->assertCreatedFailedEvent(self::$charge7);

        $this->assertEquals(Charge::FAILED, self::$charge8->refresh()->status);
        // one hour is added as invoice payment date is created one hour ahead
        $delta = strtotime('+3 days') - self::$invoice8->refresh()->next_payment_attempt;
        $this->assertBetween($delta, 0, 3, 'The next payment date should be 3 days ahead');
        $this->assertNull(self::$charge8->failure_message);
        $this->assertCreatedFailedEvent(self::$charge8);
    }

    private function assertCreatedFailedEvent(Charge $charge): void
    {
        self::getService('test.event_spool')->flush(); // write out events

        $n = Event::queryWithoutMultitenancyUnsafe()
            ->where('type_id', EventType::ChargeFailed->toInteger())
            ->where('object_id', $charge)
            ->count();
        $this->assertEquals(1, $n);
    }
}
