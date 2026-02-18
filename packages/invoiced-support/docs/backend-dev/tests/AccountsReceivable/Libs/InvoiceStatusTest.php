<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Libs\InvoiceStatusGenerator;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Tests\AppTestCase;

class InvoiceStatusTest extends AppTestCase
{
    public function testDraft(): void
    {
        $invoice = new Invoice();
        $invoice->draft = true;

        $this->assertEquals(InvoiceStatus::Draft, InvoiceStatusGenerator::get($invoice));
    }

    public function testNotSent(): void
    {
        $invoice = new Invoice();

        $this->assertEquals(InvoiceStatus::NotSent, InvoiceStatusGenerator::get($invoice));
    }

    public function testSent(): void
    {
        $invoice = new Invoice();
        $invoice->sent = true;

        $this->assertEquals(InvoiceStatus::Sent, InvoiceStatusGenerator::get($invoice));
    }

    public function testViewed(): void
    {
        $invoice = new Invoice();
        $invoice->viewed = true;
        $invoice->sent = true;

        $this->assertEquals(InvoiceStatus::Viewed, InvoiceStatusGenerator::get($invoice));
    }

    public function testPaid(): void
    {
        $invoice = new Invoice();
        $invoice->paid = true;

        $this->assertEquals(InvoiceStatus::Paid, InvoiceStatusGenerator::get($invoice));
    }

    public function testBadDebt(): void
    {
        $invoice = new Invoice();
        $invoice->paid = false;
        $invoice->closed = true;
        $this->assertEquals(InvoiceStatus::NotSent, InvoiceStatusGenerator::get($invoice));

        $invoice = new Invoice();
        $invoice->paid = false;
        $invoice->closed = true;
        $invoice->date_bad_debt = time();
        $this->assertEquals(InvoiceStatus::BadDebt, InvoiceStatusGenerator::get($invoice));
    }

    public function testPastDue(): void
    {
        $invoice = new Invoice();
        $invoice->closed = false;
        $invoice->due_date = time() - 100;

        $this->assertEquals(InvoiceStatus::PastDue, InvoiceStatusGenerator::get($invoice));
    }

    public function testPastDueNoDueDate(): void
    {
        $invoice = new Invoice();
        $invoice->paid = false;
        $invoice->due_date = null;

        $this->assertEquals(InvoiceStatus::NotSent, InvoiceStatusGenerator::get($invoice));
    }

    public function testPastDueFutureDueDate(): void
    {
        $invoice = new Invoice();
        $invoice->closed = false;
        $invoice->due_date = time() + 3600;

        $this->assertEquals(InvoiceStatus::NotSent, InvoiceStatusGenerator::get($invoice));
    }

    public function testPastDuePastDueDate(): void
    {
        $invoice = new Invoice();
        $invoice->paid = false;
        $invoice->due_date = time() - 3600;

        $this->assertEquals(InvoiceStatus::PastDue, InvoiceStatusGenerator::get($invoice));
    }

    public function testInstallmentPlanCurrent(): void
    {
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('-1 month');
        $installment1->amount = 100;
        $installment1->balance = 0;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('-10 days');
        $installment2->amount = 100;
        $installment2->balance = 0;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+1 month');
        $installment3->amount = 100;
        $installment3->balance = 100;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [$installment1, $installment2, $installment3];

        $invoice = new Invoice();
        $invoice->paid = false;
        $invoice->due_date = $installment3->date;
        $invoice->payment_plan_id = 1234;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $this->assertEquals(InvoiceStatus::NotSent, InvoiceStatusGenerator::get($invoice));
    }

    public function testInstallmentPlanPastDue(): void
    {
        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('-1 month');
        $installment1->amount = 100;
        $installment1->balance = 0;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = strtotime('-10 days');
        $installment2->amount = 100;
        $installment2->balance = 100;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+1 month');
        $installment3->amount = 100;
        $installment3->balance = 100;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [$installment1, $installment2, $installment3];

        $invoice = new Invoice();
        $invoice->paid = false;
        $invoice->due_date = $installment3->date;
        $invoice->payment_plan_id = 1234;
        $invoice->setRelation('payment_plan_id', $paymentPlan);

        $this->assertEquals(InvoiceStatus::PastDue, InvoiceStatusGenerator::get($invoice));
    }

    public function testPastDueFuturePaymentAttempt(): void
    {
        // AutoPay with a payment attempt scheduled
        $invoice = new Invoice();
        $invoice->autopay = true;
        $invoice->attempt_count = 1;
        $invoice->next_payment_attempt = time() + 3600;

        $this->assertEquals(InvoiceStatus::PastDue, InvoiceStatusGenerator::get($invoice));

        // Adding a due date should change the behavior of the past due status
        $invoice->due_date = time() + 3600;
        $this->assertEquals(InvoiceStatus::NotSent, InvoiceStatusGenerator::get($invoice));
    }

    public function testPastDueNoPaymentAttempt(): void
    {
        // AutoPay with no more payment attempts scheduled
        $invoice = new Invoice();
        $invoice->autopay = true;
        $invoice->attempt_count = 1;
        $invoice->next_payment_attempt = null;

        $this->assertEquals(InvoiceStatus::PastDue, InvoiceStatusGenerator::get($invoice));

        // Adding a due date should change the behavior of the past due status
        $invoice->due_date = time() + 3600;
        $this->assertEquals(InvoiceStatus::NotSent, InvoiceStatusGenerator::get($invoice));
    }

    public function testVoided(): void
    {
        $invoice = new Invoice();
        $invoice->voided = true;

        $this->assertEquals(InvoiceStatus::Voided, InvoiceStatusGenerator::get($invoice));
    }

    public function testINVD2879(): void
    {
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        $pending = new Transaction();
        $pending->status = Transaction::STATUS_PENDING;
        $pending->type = Transaction::TYPE_CHARGE;
        $pending->setInvoice(self::$invoice);
        $pending->amount = self::$invoice->total;
        $pending->save();

        $this->assertEquals(InvoiceStatus::Pending, InvoiceStatusGenerator::get(self::$invoice));

        self::$invoice->setFromPendingToFailed();
        $this->assertEquals(InvoiceStatus::NotSent, InvoiceStatusGenerator::get(self::$invoice));
    }
}
