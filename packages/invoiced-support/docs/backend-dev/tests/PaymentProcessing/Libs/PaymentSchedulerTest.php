<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Libs\PaymentScheduler;
use Carbon\CarbonImmutable;

class PaymentSchedulerTest extends AbstractScheduleTest
{
    public function testNextWrongCollectionMode(): void
    {
        $invoice = $this->getInvoice();
        $invoice->autopay = false;
        $this->assertNull($this->getScheduler($invoice)->next());
    }

    public function testNextDraft(): void
    {
        $invoice = $this->getInvoice();
        $invoice->draft = true;
        $this->assertNull($this->getScheduler($invoice)->next());
    }

    public function testNextClosed(): void
    {
        $invoice = $this->getInvoice();
        $invoice->closed = true;
        $this->assertNull($this->getScheduler($invoice)->next());
    }

    public function testNextVoided(): void
    {
        $invoice = $this->getInvoice();
        $invoice->voided = true;
        $this->assertNull($this->getScheduler($invoice)->next());
    }

    public function testNextPaid(): void
    {
        $invoice = $this->getInvoice();
        $invoice->paid = true;
        $this->assertNull($this->getScheduler($invoice)->next());
    }

    public function testNextPending(): void
    {
        $invoice = $this->getInvoice();
        $invoice->status = InvoiceStatus::Pending->value;
        $this->assertNull($this->getScheduler($invoice)->next());
    }

    public function testNextPaymentPlan(): void
    {
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = time();
        $installment1->balance = 25;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+1 month');
        $installment2->balance = 25;

        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+2 months');
        $installment3->balance = 50;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->status = PaymentPlan::STATUS_PENDING_SIGNUP;
        $paymentPlan->installments = [
            $installment1,
            $installment2,
            $installment3,
        ];

        $invoice = $this->getInvoice();
        $invoice->payment_plan_id = 10;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $scheduler = $this->getScheduler($invoice);
        $this->assertNull($scheduler->next());

        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;

        $this->assertEquals($installment1->date, $scheduler->next());

        $installment1->balance = 0;
        $this->assertEquals($installment2->date, $scheduler->next());
    }

    public function testNextPaymentPlanFailedAttempts(): void
    {
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = time();
        $installment1->balance = 25;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('+1 month');
        $installment2->balance = 25;

        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+2 months');
        $installment3->balance = 50;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->status = PaymentPlan::STATUS_PENDING_SIGNUP;
        $paymentPlan->installments = [
            $installment1,
            $installment2,
            $installment3,
        ];

        // Here we are setting up the invoice with a previous failed payment
        // attempt and an already scheduled next payment attempt. The
        // scheduler should not schedule any attempts prior to the next
        // due installment.
        $invoice = $this->getInvoice();
        $invoice->attempt_count = 1;
        $invoice->next_payment_attempt = 1;
        $invoice->payment_plan_id = 10;
        $invoice->setRelation('payment_plan_id', $paymentPlan);
        $scheduler = $this->getScheduler($invoice);

        $this->assertNull($scheduler->next());

        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;

        $this->assertEquals($installment1->date, $scheduler->next());

        $installment1->balance = 0;
        $this->assertEquals($installment2->date, $scheduler->next());
    }

    public function testNextInvoice(): void
    {
        $invoice = $this->getInvoice();
        $scheduler = $this->getScheduler($invoice);

        $next = $scheduler->next();
        $delta = abs(strtotime('+1 hour') - $next);

        $this->assertBetween($delta, 0, 3, 'Next payment attempt should be in the next hour');

        // next attempt should stay the same
        $invoice->next_payment_attempt = $next;
        $next = $scheduler->next();
        $this->assertEquals($invoice->next_payment_attempt, $next, 'Next payment attempt should not change');
    }

    public function testNextInvoiceAutopay(): void
    {
        $invoice = $this->getInvoice([
            'autopay_delay_days' => 1,
            'date' => strtotime('-2 days'),
        ]);

        // in the past
        $scheduler = $this->getScheduler($invoice);
        $next = $scheduler->next();
        $delta = abs(strtotime('+1 hour') - $next);
        $this->assertBetween($delta, 0, 3, 'Next payment attempt should be in the next hour');

        // now
        $invoice = $this->getInvoice([
            'autopay_delay_days' => 1,
            'date' => strtotime('-1 days'),
        ]);
        $scheduler = $this->getScheduler($invoice);
        $next = $scheduler->next();
        $delta = abs(strtotime('+1 hour') - $next);
        $this->assertBetween($delta, 0, 3, 'Next payment attempt should be in the next hour');

        // in the future
        $invoice = $this->getInvoice([
            'autopay_delay_days' => 1,
            'date' => time(),
        ]);
        $scheduler = $this->getScheduler($invoice);
        $next = $scheduler->next();
        $delta = abs(strtotime('+1 day') - $next);
        $this->assertBetween($delta, 0, 3, 'Next payment attempt should be in the next hour');
    }

    public function testNextInvoiceFutureDate(): void
    {
        $invoice = $this->getInvoice();
        $invoice->date = strtotime('+2 months');
        $scheduler = $this->getScheduler($invoice);
        $next = $scheduler->next();
        $delta = abs(strtotime('+2 months') - $next);
        $this->assertLessThan(3, $delta, 'Next payment attempt should be 2 months from now');

        // next attempt should stay the same
        $invoice->next_payment_attempt = $next;
        $next = $scheduler->next();
        $this->assertEquals($invoice->next_payment_attempt, $next, 'Next payment attempt should not change');
    }

    public function testNextInvoiceCustomerAutoPayDelaySetting(): void
    {
        $invoice = $this->getInvoice();
        $company = $invoice->tenant();
        $company->accounts_receivable_settings->payment_retry_schedule = [1, 2, 3];
        $company->accounts_receivable_settings->autopay_delay_days = 14;
        $scheduler = new PaymentScheduler($invoice);

        $invoice->customer()->autopay_delay_days = 10;
        $next = $scheduler->next();
        $delta = abs(strtotime('+10 days') - $next);
        $this->assertBetween($delta, 0, 3, 'Next payment attempt should be in the next 10 days');

        // past invoice date
        $invoice->date = strtotime('-10 days');
        $next = (int) $scheduler->next();
        $delta = CarbonImmutable::createFromTimestamp($next)->diffInHours(CarbonImmutable::now()->addHour());
        $this->assertEquals(0, $delta, 'Next payment attempt should be in today (next hour');

        // future invoice date
        $invoice->date = strtotime('+20 days');
        $next = $scheduler->next();
        $delta = abs(strtotime('+30 days') - $next);
        $this->assertLessThan(3, $delta, 'Next payment attempt should be in the next 30 days');

        // next attempt should stay the same
        $invoice->next_payment_attempt = $next;
        $next = $scheduler->next();
        $this->assertEquals($invoice->next_payment_attempt, $next, 'Next payment attempt should not change');
    }

    public function testNextInvoiceGlobalAutoPayDelaySetting(): void
    {
        $invoice = $this->getInvoice();
        $company = $invoice->tenant();
        $company->accounts_receivable_settings->payment_retry_schedule = [1, 2, 3];
        $company->accounts_receivable_settings->autopay_delay_days = 14;
        $scheduler = new PaymentScheduler($invoice);

        $next = $scheduler->next();
        $delta = abs(strtotime('+14 days') - $next);
        $this->assertBetween($delta, 0, 3, 'Next payment attempt should be in the next 14 days');

        // past invoice date
        $invoice->date = strtotime('-10 days');
        $next = $scheduler->next();
        $delta = abs(strtotime('+4 days') - $next);
        $this->assertLessThan(3, $delta, 'Next payment attempt should be in the next 4 days');

        // future invoice date
        $invoice->date = strtotime('+20 days');
        $next = $scheduler->next();
        $delta = abs(strtotime('+34 days') - $next);
        $this->assertLessThan(3, $delta, 'Next payment attempt should be in the next 34 days');

        // next attempt should stay the same
        $invoice->next_payment_attempt = $next;
        $next = $scheduler->next();
        $this->assertEquals($invoice->next_payment_attempt, $next, 'Next payment attempt should not change');
    }

    public function testNextFailedAttemptInvoice(): void
    {
        $invoice = $this->getInvoice();
        $time = time();
        $invoice->next_payment_attempt = $time;
        $invoice->attempt_count = 1;
        $scheduler = $this->getScheduler($invoice);
        $next = $scheduler->failed()->next();
        $this->assertEquals($time + 86400, $next, 'Next payment attempt should be in 1 day');

        // next attempt should stay the same
        $this->assertEquals($time, $scheduler->next(), 'Next payment attempt should not change');

        $invoice->attempt_count = 2;
        $next = $scheduler->failed()->next();
        $this->assertEquals($time + 172800, $next, 'Next payment attempt should be in 2 days');

        $invoice->attempt_count = 3;
        $next = $scheduler->failed()->next();
        $this->assertEquals($time + 259200, $next, 'Next payment attempt should be in 3 days');

        $invoice->attempt_count = 4;
        $next = $scheduler->failed()->next();
        $this->assertNull($next, 'Next payment attempt should be null');
    }

    public function testNextFailedAttemptPaymentPlan(): void
    {
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = 2;
        $installment1->balance = 25;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = 2592002;
        $installment2->balance = 25;

        $installment3 = new PaymentPlanInstallment();
        $installment3->date = 5184002;
        $installment3->balance = 50;

        $paymentPlan = new PaymentPlan();
        $paymentPlan->status = PaymentPlan::STATUS_ACTIVE;
        $paymentPlan->installments = [
            $installment1,
            $installment2,
            $installment3,
        ];

        $invoice = $this->getInvoice();
        $invoice->payment_plan_id = 10;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $time = time();
        $invoice->next_payment_attempt = $time;
        $invoice->attempt_count = 1;

        $scheduler = $this->getScheduler($invoice);
        $next = $scheduler->failed()->next();
        $this->assertEquals($time + 86400, $next, 'Next payment attempt should be in 1 day');

        // next attempt should stay the same
        $this->assertEquals($time, $scheduler->next(), 'Next payment attempt should not change');

        // ...until it's been saved on the invoice
        $invoice->next_payment_attempt = $next;
        $this->assertEquals($time + 86400, $scheduler->next(), 'Next payment should be previously scheduled value');

        $invoice->next_payment_attempt = $time;
        $invoice->attempt_count = 2;
        $next = $scheduler->failed()->next();
        $this->assertEquals($time + 172800, $next, 'Next payment attempt should be in 2 days');

        $invoice->attempt_count = 3;
        $next = $scheduler->failed()->next();
        $this->assertEquals($time + 259200, $next, 'Next payment attempt should be in 3 days');

        $invoice->attempt_count = 4;
        $next = $scheduler->failed()->next();
        $this->assertNull($next, 'Next payment attempt should be null');

        // after all payment attempts have been exhausted then another
        // attempt should not be scheduled
        $invoice->attempt_count = 4;
        $invoice->next_payment_attempt = null;
        $this->assertNull($scheduler->next(), 'Next payment attempt should still be null');
    }

    private function getScheduler(Invoice $invoice): PaymentScheduler
    {
        return new PaymentScheduler($invoice);
    }
}
