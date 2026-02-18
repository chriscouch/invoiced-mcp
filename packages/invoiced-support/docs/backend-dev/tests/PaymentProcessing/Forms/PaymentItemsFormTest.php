<?php

namespace App\Tests\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Forms\PaymentItemsFormBuilder;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\Tests\AppTestCase;

class PaymentItemsFormTest extends AppTestCase
{
    private static Invoice $invoice2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasUnappliedCreditNote();
        self::acceptsCreditCards();
        self::$company->features->enable('estimates');

        self::hasEstimate();
        self::$estimate->approved = 'ABC';
        self::$estimate->deposit = 1000;
        self::$estimate->saveOrFail();

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
    }

    private function getFormBuilder(?Customer $customer = null, ?PaymentFormSettings $settings = null): PaymentItemsFormBuilder
    {
        $customer = $customer ?? self::$customer;
        $settings ??= new PaymentFormSettings(
            self::$company,
            false,
            true,
            false,
            false
        );

        return new PaymentItemsFormBuilder($settings, $customer, [$customer->id]);
    }

    public function testSelectFromNumber(): void
    {
        $builder = $this->getFormBuilder();
        $form = $builder->build();
        $this->assertCount(0, $form->selectedDocuments);

        $builder->selectCreditNoteByNumber('CN-00001');
        $builder->selectCreditNoteByNumber('CN-DOESNOTEXIST');
        $builder->selectInvoiceByNumber('INV-00001');
        $builder->selectInvoiceByNumber('INV-DOESNOTEXIST');
        $builder->selectEstimateByNumber('EST-00001');
        $builder->selectEstimateByNumber('EST-DOESNOTEXIST');
        $builder->selectAdvancePayment();
        $builder->selectCreditBalance();

        $form = $builder->build();

        $this->assertCount(5, $form->selectedDocuments);
        $this->assertContains(self::$creditNote->client_id, $form->selectedDocuments);
        $this->assertContains(self::$invoice->client_id, $form->selectedDocuments);
        $this->assertContains(self::$estimate->client_id, $form->selectedDocuments);
        $this->assertTrue($form->isAdvancePaymentSelected());
        $this->assertTrue($form->isCreditBalanceSelected());
    }

    public function testAddOpenItems(): void
    {
        $form = $this->getFormBuilder()->build();
        $this->assertEquals(self::$customer, $form->customer);
        $this->assertCount(4, $form->documents);
        $this->assertEquals($form->documents[0]->id, self::$estimate->id);
        $this->assertEquals(self::$invoice->id(), $form->documents[1]->id());
        $this->assertEquals(self::$invoice2->id(), $form->documents[2]->id());
        $this->assertEquals($form->documents[3]->id, self::$creditNote->id);
        $this->assertEquals(0, $form->creditBalance->amount);
        $this->assertEquals('usd', $form->creditBalance->currency);
    }

    public function testAddOpenItemsNoPaymentInfoAutoPay(): void
    {
        $customer = new Customer();
        $customer->name = 'Test';
        $customer->autopay = true;
        $customer->saveOrFail();

        $invoice = new Invoice();
        $invoice->number = '1';
        $invoice->setCustomer($customer);
        $invoice->attempt_count = 2;
        $invoice->next_payment_attempt = strtotime('+3 days');
        $invoice->items = [['unit_cost' => 100]];
        $invoice->saveOrFail();

        $form = $this->getFormBuilder($customer)->build();
        $this->assertEquals($customer, $form->customer);
        $this->assertCount(1, $form->documents);
        $this->assertEquals($invoice->id(), $form->documents[0]->id());
        $this->assertEquals(0, $form->creditBalance->amount);
        $this->assertEquals('usd', $form->creditBalance->currency);
    }

    public function testAdvancePayment(): void
    {
        $settings = new PaymentFormSettings(
            self::$company,
            false,
            true,
            true,
            false
        );
        $form = $this->getFormBuilder(self::$customer, $settings)->build();
        $this->assertTrue($form->advancePayment);
    }

    public function testCreditBalance(): void
    {
        self::hasCredit();
        $form = $this->getFormBuilder(self::$customer)->build();
        $this->assertEquals(10000, $form->creditBalance->amount);
        $this->assertEquals('usd', $form->creditBalance->currency);
    }
}
