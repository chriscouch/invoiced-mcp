<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Chasing\Enums\ChasingChannelEnum;
use App\Chasing\Enums\ChasingTypeEnum;
use App\Chasing\Models\ChasingCadence;
use App\Chasing\Models\ChasingCadenceStep;
use App\Chasing\Models\ChasingStatistic;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ChasingInvoiceListenerTest extends AppTestCase
{
    private static ChasingCadence $cadence;
    private static int $firstStepId;
    private static int $secondStepId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        self::$cadence = new ChasingCadence();
        self::$cadence->name = 'Test';
        self::$cadence->time_of_day = 7;
        self::$cadence->steps = [
            [
                'name' => 'Mail',
                'schedule' => 'age:7',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
            [
                'name' => 'Mail',
                'schedule' => 'age:8',
                'action' => ChasingCadenceStep::ACTION_MAIL,
            ],
        ];
        self::$cadence->saveOrFail();
        self::$firstStepId = (int) self::$cadence->getSteps()[0]->id();
        self::$secondStepId = (int) self::$cadence->getSteps()[1]->id();

        self::hasCustomer();
        self::$customer->chasing_cadence_id = (int) self::$cadence->id();
        self::$customer->saveOrFail();
    }

    private function createChasingStatistics(Invoice $invoice): ChasingStatistic
    {
        $statistics = new ChasingStatistic();
        $statistics->type = ChasingTypeEnum::Customer->value;
        $statistics->customer_id = self::$customer->id;
        $statistics->invoice_id = $invoice->id;
        $statistics->cadence_id = self::$cadence->id;
        $statistics->cadence_step_id = 1;
        $statistics->channel = ChasingChannelEnum::Email->value;
        $statistics->date = CarbonImmutable::now()->toIso8601String();
        $statistics->saveOrFail();

        return $statistics;
    }

    public function testInvoicePaid(): void
    {
        self::$customer->next_chase_step = null;
        self::$customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $statistics = $this->createChasingStatistics($invoice);

        $payment = new Payment();
        $payment->amount = $invoice->balance;
        $payment->applied_to = [['invoice' => $invoice, 'type' => 'invoice', 'amount' => $invoice->balance]];
        $payment->saveOrFail();

        // when the balance is paid in full the next step should be set to the first step in the cadence
        $this->assertEquals(self::$firstStepId, self::$customer->refresh()->next_chase_step);

        $statistics->refresh();
        $this->assertTrue($statistics->payment_responsible);
    }

    public function testInstallmentPaid(): void
    {
        $customer = new Customer();
        $customer->name = 'Installment Paid';
        $customer->country = 'US';
        $customer->chasing_cadence = (int) self::$cadence->id();
        $customer->next_chase_step = self::$secondStepId;
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->date = strtotime('-3 months');
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = $invoice->date;
        $installment1->amount = 100;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('-1 month');
        $installment2->amount = 50;

        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+1 month');
        $installment3->amount = 50;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->invoice_id = (int) $invoice->id();
        $paymentPlan->installments = [
            $installment1,
            $installment3,
            $installment2,
        ];
        $invoice->attachPaymentPlan($paymentPlan, false, true);
        $statistics = $this->createChasingStatistics($invoice);

        // paying the first installment leaves the invoice as past due
        // chasing should continue to be on the second step
        $payment = new Payment();
        $payment->amount = 100;
        $payment->applied_to = [['invoice' => $invoice, 'type' => 'invoice', 'amount' => 100]];
        $payment->saveOrFail();

        $this->assertEquals(self::$secondStepId, $customer->refresh()->next_chase_step);

        // INVD-1399 change
        // age calculation is changed, so installment age would reflect upcoming balance
        // which will be above zero. So we need to pay full amount to refresh invoice
        $payment = new Payment();
        $payment->amount = 100;
        $payment->applied_to = [['invoice' => $invoice, 'type' => 'invoice', 'amount' => 100]];
        $payment->saveOrFail();

        $this->assertEquals(self::$firstStepId, $customer->refresh()->next_chase_step);
        $statistics->refresh();
        $this->assertTrue($statistics->payment_responsible);
    }

    public function testInvoiceClosed(): void
    {
        self::$customer->next_chase_step = null;
        self::$customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();
        $statistics = $this->createChasingStatistics($invoice);

        $invoice->closed = true;
        $invoice->saveOrFail();

        // when the balance is paid in full the next step should be set to the first step in the cadence
        $this->assertEquals(self::$firstStepId, self::$customer->refresh()->next_chase_step);
        $statistics->refresh();
        $this->assertFalse($statistics->payment_responsible);
    }

    public function testInvoiceDeleted(): void
    {
        self::$customer->next_chase_step = null;
        self::$customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();
        $statistics = $this->createChasingStatistics($invoice);

        $invoice->delete();

        // when the balance is paid in full the next step should be set to the first step in the cadence
        $this->assertEquals(self::$firstStepId, self::$customer->refresh()->next_chase_step);
        $statistics->refresh();
        $this->assertFalse($statistics->payment_responsible);
    }
}
