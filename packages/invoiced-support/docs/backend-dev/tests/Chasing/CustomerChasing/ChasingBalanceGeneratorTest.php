<?php

namespace App\Tests\Chasing\CustomerChasing;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Chasing\CustomerChasing\ChasingBalanceGenerator;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Tests\AppTestCase;

class ChasingBalanceGeneratorTest extends AppTestCase
{
    private static ChasingBalanceGenerator $generator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::acceptsCreditCards();

        self::$generator = new ChasingBalanceGenerator();
    }

    public function testAccountBalanceNothingDue(): void
    {
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->saveOrFail();

        $accountBalance = self::$generator->generate($customer, 'usd');
        $this->assertTrue($accountBalance->getBalance()->isZero());
        $this->assertEquals(0, $accountBalance->getAge());
        $this->assertNull($accountBalance->getPastDueAge());
        $this->assertFalse($accountBalance->isPastDue());
        $this->assertCount(0, $accountBalance->getInvoices());
    }

    public function testAccountBalanceOpen(): void
    {
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->date = strtotime('-1 day');
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $draft = new Invoice();
        $draft->draft = true;
        $draft->setCustomer($customer);
        $draft->items = [['unit_cost' => 100]];
        $draft->saveOrFail();

        $paid = new Invoice();
        $paid->setCustomer($customer);
        $paid->items = [['unit_cost' => 100]];
        $paid->saveOrFail();

        $payment = new Payment();
        $payment->amount = 100;
        $payment->applied_to = [['invoice' => $paid, 'type' => 'invoice', 'amount' => 100]];
        $payment->saveOrFail();

        $pending = new Invoice();
        $pending->setCustomer($customer);
        $pending->items = [['unit_cost' => 100]];
        $pending->saveOrFail();

        $pendingTxn = new Transaction();
        $pendingTxn->setInvoice($pending);
        $pendingTxn->amount = 25;
        $pendingTxn->status = Transaction::STATUS_PENDING;
        $pendingTxn->saveOrFail();

        $pending2 = new Invoice();
        $pending2->setCustomer($customer);
        $pending2->items = [['unit_cost' => 100]];
        $pending2->saveOrFail();

        $pendingTxn2 = new Transaction();
        $pendingTxn2->setInvoice($pending2);
        $pendingTxn2->amount = 100;
        $pendingTxn2->status = Transaction::STATUS_PENDING;
        $pendingTxn2->saveOrFail();

        $voidedInvoice = new Invoice();
        $voidedInvoice->setCustomer($customer);
        $voidedInvoice->items = [['unit_cost' => 1000]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();

        $accountBalance = self::$generator->generate($customer, 'usd');
        $this->assertEquals('usd', $accountBalance->getBalance()->currency);
        $this->assertEquals(17500, $accountBalance->getBalance()->amount);
        $this->assertEquals('usd', $accountBalance->getPastDueBalance()->currency);
        $this->assertEquals(0, $accountBalance->getPastDueBalance()->amount);
        $this->assertEquals(1, $accountBalance->getAge());
        $this->assertNull($accountBalance->getPastDueAge());
        $this->assertFalse($accountBalance->isPastDue());
        $invoices = $accountBalance->getInvoices();
        $this->assertCount(2, $invoices);
        $this->assertEquals($invoice->id(), $invoices[0]->id());
        $this->assertEquals($pending->id(), $invoices[1]->id());
    }

    public function testAccountBalancePastDue(): void
    {
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->date = strtotime('-10 days');
        $invoice->due_date = strtotime('-1 day');
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $accountBalance = self::$generator->generate($customer, 'usd');
        $this->assertEquals('usd', $accountBalance->getBalance()->currency);
        $this->assertEquals(10000, $accountBalance->getBalance()->amount);
        $this->assertEquals('usd', $accountBalance->getPastDueBalance()->currency);
        $this->assertEquals(10000, $accountBalance->getPastDueBalance()->amount);
        $this->assertEquals(10, $accountBalance->getAge());
        $this->assertEquals(1, $accountBalance->getPastDueAge());
        $this->assertTrue($accountBalance->isPastDue());
        $invoices = $accountBalance->getInvoices();
        $this->assertCount(1, $invoices);
        $this->assertEquals($invoice->id(), $invoices[0]->id());
    }

    public function testAccountBalanceWithInstallments(): void
    {
        $customer = new Customer();
        $customer->name = 'Installments';
        $customer->country = 'US';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->date = time() - 90 * 86400;
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = time() - 60 * 86400;
        $installment1->amount = 100;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = time() - 30 * 86400;
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

        $accountBalance = self::$generator->generate($customer, 'usd');
        $this->assertEquals('usd', $accountBalance->getBalance()->currency);
        $this->assertEquals(20000, $accountBalance->getBalance()->amount);
        $this->assertEquals('usd', $accountBalance->getPastDueBalance()->currency);
        $this->assertEquals(15000, $accountBalance->getPastDueBalance()->amount);
        $this->assertEquals(90, $accountBalance->getAge());
        $this->assertEquals(60, $accountBalance->getPastDueAge());
        $this->assertTrue($accountBalance->isPastDue());
        $invoices = $accountBalance->getInvoices();
        $this->assertCount(1, $invoices);
        $this->assertEquals($invoice->id(), $invoices[0]->id());
    }

    public function testAccountBalanceWithInstallmentsPartialPayment(): void
    {
        $customer = new Customer();
        $customer->name = 'Installments';
        $customer->country = 'US';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->date = time() - 90 * 86400;
        $invoice->items = [['unit_cost' => 200]];
        $invoice->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = $invoice->date;
        $installment1->amount = 100;

        $installment2 = new PaymentPlanInstallment();
        $installment2->date = time() - 30 * 86400;
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

        $payment = new Payment();
        $payment->amount = 150;
        $payment->applied_to = [['invoice' => $invoice, 'type' => 'invoice', 'amount' => 150]];
        $payment->saveOrFail();

        $accountBalance = self::$generator->generate($customer, 'usd');
        $this->assertEquals('usd', $accountBalance->getBalance()->currency);
        $this->assertEquals(5000, $accountBalance->getBalance()->amount);
        $this->assertEquals('usd', $accountBalance->getPastDueBalance()->currency);
        $this->assertEquals(0, $accountBalance->getPastDueBalance()->amount);
        $this->assertEquals(30, $accountBalance->getAge());
        $this->assertNull($accountBalance->getPastDueAge());
        $this->assertFalse($accountBalance->isPastDue());
        $invoices = $accountBalance->getInvoices();
        $this->assertCount(1, $invoices);
        $this->assertEquals($invoice->id(), $invoices[0]->id());
    }

    public function testAccountBalanceWithAutoPay(): void
    {
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->country = 'US';
        $customer->autopay = true;
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $accountBalance = self::$generator->generate($customer, 'usd');
        $this->assertTrue($accountBalance->getBalance()->isZero());
        $this->assertTrue($accountBalance->getPastDueBalance()->isZero());
        $this->assertEquals(0, $accountBalance->getAge());
        $this->assertNull($accountBalance->getPastDueAge());
        $this->assertFalse($accountBalance->isPastDue());
        $this->assertCount(0, $accountBalance->getInvoices());
    }

    public function testAccountBalanceOpenCreditNotes(): void
    {
        $customer = new Customer();
        $customer->name = 'Credit Note Test';
        $customer->country = 'US';
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->date = strtotime('-1 day');
        $invoice->setCustomer($customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $invoice2 = new Invoice();
        $invoice2->date = strtotime('-10 days');
        $invoice2->due_date = strtotime('-1 day');
        $invoice2->setCustomer($customer);
        $invoice2->items = [['unit_cost' => 100]];
        $invoice2->saveOrFail();

        $creditNote = new CreditNote();
        $creditNote->date = strtotime('-15 days');
        $creditNote->setCustomer($customer);
        $creditNote->items = [['unit_cost' => 50.54]];
        $creditNote->saveOrFail();

        $accountBalance = self::$generator->generate($customer, 'usd');
        $this->assertEquals('usd', $accountBalance->getBalance()->currency);
        $this->assertEquals(14946, $accountBalance->getBalance()->amount);
        $this->assertEquals('usd', $accountBalance->getPastDueBalance()->currency);
        $this->assertEquals(4946, $accountBalance->getPastDueBalance()->amount);
        $this->assertEquals(10, $accountBalance->getAge());
        $this->assertEquals(1, $accountBalance->getPastDueAge());
        $this->assertTrue($accountBalance->isPastDue());
        $invoices = $accountBalance->getInvoices();
        $this->assertCount(2, $invoices);
        $this->assertEquals($invoice2->id(), $invoices[0]->id());
        $this->assertEquals($invoice->id(), $invoices[1]->id());
    }
}
