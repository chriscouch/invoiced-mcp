<?php

namespace App\Tests\PaymentProcessing\Forms;

use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\PaymentProcessing\Enums\PaymentAmountOption;
use App\PaymentProcessing\Forms\PaymentAmountFormBuilder;
use App\PaymentProcessing\ValueObjects\PaymentFormSettings;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class PaymentAmountFormTest extends AppTestCase
{
    private static Invoice $paymentPlanInvoice;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasEstimate();
        self::hasUnappliedCreditNote();

        $paymentPlanInvoice = new Invoice();
        $paymentPlanInvoice->setCustomer(self::$customer);
        $paymentPlanInvoice->date = (int) strtotime('-8 days');
        $paymentPlanInvoice->due_date = (int) strtotime('-8 days');
        $paymentPlanInvoice->items = [['unit_cost' => 150]];
        $paymentPlanInvoice->saveOrFail();
        self::$paymentPlanInvoice = $paymentPlanInvoice;

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = (new CarbonImmutable('2020-01-01'))->getTimestamp();
        $installment1->amount = 50;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = CarbonImmutable::now()->addMonth()->getTimestamp();
        $installment2->amount = 50;
        $installment3 = new PaymentPlanInstallment();
        $installment3->date = CarbonImmutable::now()->addMonths(2)->getTimestamp();
        $installment3->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
            $installment3,
        ];
        $paymentPlanInvoice->attachPaymentPlan($paymentPlan, false, true);
    }

    private function getFormBuilder(?PaymentFormSettings $settings = null): PaymentAmountFormBuilder
    {
        $settings ??= new PaymentFormSettings(
            self::$company,
            false,
            false,
            false,
            false
        );

        return new PaymentAmountFormBuilder($settings, self::$customer);
    }

    public function testInvoicesWithPartialPayments(): void
    {
        $settings = new PaymentFormSettings(
            self::$company,
            true,
            false,
            false,
            false
        );

        $builder = $this->getFormBuilder($settings);
        $builder->addInvoiceByClientId(self::$invoice->client_id);

        $form = $builder->build();
        $this->assertCount(1, $form->lineItems);
        $this->assertEquals(self::$invoice->id, $form->lineItems[0]->document?->id);
        $this->assertEquals([
                ['type' => PaymentAmountOption::PayInFull, 'amount' => new Money('usd', 10000)],
                ['type' => PaymentAmountOption::PayPartial, 'amount' => null],
            ], $form->lineItems[0]->options);
        $this->assertTrue($form->hasAvailableChoices());
    }

    public function testInvoicesPaymentPlan(): void
    {
        $settings = new PaymentFormSettings(
            self::$company,
            true,
            false,
            false,
            false
        );

        $builder = $this->getFormBuilder($settings);
        $builder->addInvoiceByClientId(self::$paymentPlanInvoice->client_id);

        $form = $builder->build();
        $this->assertCount(1, $form->lineItems);
        $this->assertEquals(self::$paymentPlanInvoice->id, $form->lineItems[0]->document?->id);
        $this->assertEquals([
                ['type' => PaymentAmountOption::PaymentPlan, 'amount' => new Money('usd', 10000)],
                ['type' => PaymentAmountOption::PayInFull, 'amount' => new Money('usd', 15000)],
                ['type' => PaymentAmountOption::PayPartial, 'amount' => null],
            ], $form->lineItems[0]->options);
        $this->assertTrue($form->hasAvailableChoices());
    }

    public function testInvoicesWithoutPartialPayments(): void
    {
        $builder = $this->getFormBuilder();
        $builder->addInvoiceByClientId(self::$invoice->client_id);

        $form = $builder->build();
        $this->assertCount(1, $form->lineItems);
        $this->assertEquals(self::$invoice->id, $form->lineItems[0]->document?->id);
        $this->assertEquals([['type' => PaymentAmountOption::PayInFull, 'amount' => new Money('usd', 10000)]], $form->lineItems[0]->options);
        $this->assertFalse($form->hasAvailableChoices());
    }

    public function testEstimates(): void
    {
        $builder = $this->getFormBuilder();
        $builder->addEstimateByClientId(self::$estimate->client_id);

        $form = $builder->build();
        $this->assertCount(1, $form->lineItems);
        $this->assertEquals(self::$estimate->id, $form->lineItems[0]->document?->id);
        $this->assertEquals([['type' => PaymentAmountOption::PayInFull, 'amount' => new Money('usd', 0)]], $form->lineItems[0]->options);
        $this->assertFalse($form->hasAvailableChoices());
    }

    public function testCreditNotes(): void
    {
        $settings = new PaymentFormSettings(
            self::$company,
            false,
            true,
            false,
            false
        );

        $builder = $this->getFormBuilder($settings);
        $builder->addCreditNoteByClientId(self::$creditNote->client_id);

        $form = $builder->build();
        $this->assertCount(1, $form->lineItems);
        $this->assertEquals(self::$creditNote->id, $form->lineItems[0]->document?->id);
        $this->assertEquals([['type' => PaymentAmountOption::ApplyCredit, 'amount' => new Money('usd', -10000)]], $form->lineItems[0]->options);
        $this->assertFalse($form->hasAvailableChoices());
    }

    public function testCreditBalances(): void
    {
        $settings = new PaymentFormSettings(
            self::$company,
            false,
            true,
            false,
            false
        );

        self::$creditNote->deleteOrFail();
        self::hasCredit();

        $builder = $this->getFormBuilder($settings);
        $builder->addCreditBalance();

        $form = $builder->build();
        $this->assertCount(1, $form->lineItems);
        $this->assertNull($form->lineItems[0]->document);
        $this->assertEquals('creditBalance', $form->lineItems[0]->nonDocumentType);
        $this->assertEquals([['type' => PaymentAmountOption::ApplyCredit, 'amount' => new Money('usd', -10000)]], $form->lineItems[0]->options);
        $this->assertFalse($form->hasAvailableChoices());
    }

    public function testAdvancePayments(): void
    {
        $settings = new PaymentFormSettings(
            self::$company,
            false,
            false,
            true,
            false
        );

        $builder = $this->getFormBuilder($settings);
        $builder->addAdvancePayment();

        $form = $builder->build();
        $this->assertCount(1, $form->lineItems);
        $this->assertNull($form->lineItems[0]->document);
        $this->assertEquals('advance', $form->lineItems[0]->nonDocumentType);
        $this->assertEquals([['type' => PaymentAmountOption::AdvancePayment, 'amount' => null]], $form->lineItems[0]->options);
        $this->assertTrue($form->hasAvailableChoices());
    }
}
