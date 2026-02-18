<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\Core\Cron\ValueObjects\Run;
use App\EntryPoint\CronJob\MarkPaymentPlanInvoicesPastDue;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Tests\AppTestCase;

class MarkPaymentPlanInvoicesPastDueTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();

        // create a payment plan invoice that will be past due in 1 second
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 300]];
        $invoice->saveOrFail();
        self::$invoice = $invoice;

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = strtotime('-1 month');
        $installment1->amount = 100;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = time() - 1;
        $installment2->amount = 100;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = strtotime('+1 month');
        $installment3->amount = 100;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [$installment1, $installment2, $installment3];

        $invoice->attachPaymentPlan($paymentPlan, false, true);

        $payment = new Transaction();
        $payment->setInvoice($invoice);
        $payment->amount = 100;
        $payment->saveOrFail();

        // hack to unmark the invoice as past due
        self::getService('test.database')->update('Invoices', ['status' => InvoiceStatus::NotSent->value], ['id' => $invoice->id()]);
    }

    public function testGetCompanies(): void
    {
        $job = $this->getJob();
        $companies = $job->getCompanies();
        $this->assertTrue(in_array(self::$company->id, $companies));
    }

    public function testGetDocuments(): void
    {
        $job = $this->getJob();
        $invoices = $job->getDocuments(self::$company);
        $this->assertCount(1, $invoices);
        $this->assertInstanceOf(Invoice::class, $invoices[0]);
        $this->assertEquals(self::$invoice->id(), $invoices[0]->id());
    }

    public function testExecute(): void
    {
        $job = $this->getJob();
        $job->execute(new Run());
        $this->assertEquals(1, $job->getTaskCount());
        $this->assertEquals(InvoiceStatus::PastDue->value, self::$invoice->refresh()->status);
    }

    private function getJob(): MarkPaymentPlanInvoicesPastDue
    {
        return self::getService('test.mark_payment_plan_invoices_past_due');
    }
}
