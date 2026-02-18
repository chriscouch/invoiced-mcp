<?php

namespace App\Tests\Chasing\LateFees;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\Chasing\LateFees\LateFeeApplierLegacy;
use App\Chasing\LateFees\LateFeeAssessor;
use App\Chasing\Models\LateFee;
use App\Chasing\Models\LateFeeSchedule;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class LateFeeAssessorLegacyTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();

        self::$lateFeeSchedule = new LateFeeSchedule();
        self::$lateFeeSchedule->name = 'Test';
        self::$lateFeeSchedule->start_date = (new CarbonImmutable('-3 years'));
        self::$lateFeeSchedule->grace_period = 7;
        self::$lateFeeSchedule->amount = 5;
        self::$lateFeeSchedule->is_percent = true;
        self::$lateFeeSchedule->recurring_days = 10;
        self::$lateFeeSchedule->saveOrFail();

        self::$customer->late_fee_schedule = self::$lateFeeSchedule;
        self::$customer->saveOrFail();

        self::$invoice = new Invoice();
        self::$invoice->setCustomer(self::$customer);
        self::$invoice->items = [['unit_cost' => 100]];
        self::$invoice->date = (int) strtotime('-8 days');
        self::$invoice->due_date = (int) strtotime('-8 days');
        self::$invoice->saveOrFail();

        self::mockLateFeeLegacy(self::$invoice);

        // Invoices with date before start date SHOULD NOT get late fees
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->date = (int) strtotime('-5 years');
        $invoice->due_date = (int) strtotime('-5 years');
        $invoice->saveOrFail();

        // Invoices with due date before grace period SHOULD NOT get late fees
        for ($i = 0; $i <= 7; ++$i) {
            $invoice = new Invoice();
            $invoice->setCustomer(self::$customer);
            $invoice->items = [['unit_cost' => 100]];
            $invoice->date = (int) strtotime("-$i days");
            $invoice->due_date = (int) strtotime("-$i days");
            $invoice->saveOrFail();
        }

        // Closed invoices SHOULD NOT get late fees
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->date = (int) strtotime('-8 days');
        $invoice->due_date = (int) strtotime('-8 days');
        $invoice->closed = true;
        $invoice->saveOrFail();

        // Draft invoices SHOULD NOT get late fees
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->date = (int) strtotime('-8 days');
        $invoice->due_date = (int) strtotime('-8 days');
        $invoice->draft = true;
        $invoice->saveOrFail();

        // Paid / $0 invoices SHOULD NOT get late fees
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->date = (int) strtotime('-8 days');
        $invoice->due_date = (int) strtotime('-8 days');
        $invoice->saveOrFail();

        // Voided invoices SHOULD NOT get late fees
        $voidedInvoice = new Invoice();
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 1000]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();

        // Invoices with late fees disabled SHOULD NOT get late fees
        $disabledInvoice = new Invoice();
        $disabledInvoice->setCustomer(self::$customer);
        $disabledInvoice->items = [['unit_cost' => 1000]];
        $disabledInvoice->late_fees = false;
        $disabledInvoice->saveOrFail();

        // Payment plan invoices SHOULD NOT get late fees
        $paymentPlanInvoice = new Invoice();
        $paymentPlanInvoice->setCustomer(self::$customer);
        $paymentPlanInvoice->date = (int) strtotime('-8 days');
        $paymentPlanInvoice->due_date = (int) strtotime('-8 days');
        $paymentPlanInvoice->items = [['unit_cost' => 100]];
        $paymentPlanInvoice->saveOrFail();

        $installment1 = new PaymentPlanInstallment();
        $installment1->date = (int) mktime(0, 0, 0, 3, 12, 2019);
        $installment1->amount = 50;
        $installment2 = new PaymentPlanInstallment();
        $installment2->date = (int) mktime(0, 0, 0, 4, 12, 2019);
        $installment2->amount = 50;
        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = [
            $installment1,
            $installment2,
        ];
        $paymentPlanInvoice->attachPaymentPlan($paymentPlan, false, true);
    }

    private static function mockLateFeeLegacy(Invoice $invoice): void
    {
        $lineItem = new LineItem();
        $lineItem->type = 'late_fee';
        $lineItem->name = 'Late fee';
        $lineItem->quantity = 1;
        $lineItem->unit_cost = 0;
        $lineItem->discountable = false;
        $lineItem->taxable = false;
        $lineItem->order = 10000; // try to make this last
        $lineItem->setParent($invoice);
        $lineItem->saveOrFail();

        $lateFee = new LateFee();
        $lateFee->customer_id = $invoice->customer;
        $lateFee->invoice_id = (int) $invoice->id();
        $lateFee->line_item_id = (int) $lineItem->id();
        $lateFee->version = 1;
        $lateFee->saveOrFail();
    }

    private function getAssessor(): LateFeeAssessor
    {
        return self::getService('test.late_fee_assessor');
    }

    public function testGetLateInvoices(): void
    {
        $assessor = $this->getAssessor();
        /** @var Invoice[] $invoices */
        $invoices = $assessor->getLateInvoices(self::$lateFeeSchedule);
        $this->assertCount(1, $invoices);
        $this->assertEquals(self::$invoice->id(), $invoices[0]->id());
    }

    public function testApplyLateFees(): void
    {
        $assessor = $this->getAssessor();
        $this->assertEquals(1, $assessor->assess(self::$lateFeeSchedule));

        $items = self::$invoice->items(true);
        $this->assertCount(2, $items);

        $expected = [
            'catalog_item' => null,
            'type' => 'late_fee',
            'name' => 'Late fee',
            'description' => null,
            'discountable' => false,
            'discounts' => [],
            'taxable' => false,
            'taxes' => [],
            'quantity' => 1.0,
            'unit_cost' => 5.0,
            'amount' => 5.0,
            'metadata' => new \stdClass(),
        ];
        unset($items[1]['id']);
        unset($items[1]['object']);
        unset($items[1]['created_at']);
        unset($items[1]['updated_at']);
        $this->assertEquals($expected, $items[1]);
    }

    public function testAddLateFeeToInvoice(): void
    {
        $assessor = $this->getAssessor();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100], ['unit_cost' => 50]];
        $invoice->due_date = strtotime('-10 days');
        $invoice->saveOrFail();

        self::mockLateFeeLegacy($invoice);

        $this->assertTrue($assessor->addLateFeeToInvoice($invoice, self::$lateFeeSchedule));

        // should add a line item
        $items = $invoice->items(true);
        $this->assertCount(3, $items);

        $expected = [
            'catalog_item' => null,
            'type' => 'late_fee',
            'name' => 'Late fee',
            'description' => null,
            'discountable' => false,
            'discounts' => [],
            'taxable' => false,
            'taxes' => [],
            'quantity' => 1.0,
            'unit_cost' => 7.5,
            'amount' => 7.5,
            'metadata' => new \stdClass(),
        ];
        unset($items[2]['id']);
        unset($items[2]['object']);
        unset($items[2]['created_at']);
        unset($items[2]['updated_at']);
        $this->assertEquals($expected, $items[2]);

        // should create a late fee object
        $lateFee = LateFee::where('invoice_id', $invoice->id())->oneOrNull();
        $this->assertInstanceOf(LateFee::class, $lateFee);
        $this->assertEquals(self::$customer->id(), $lateFee->customer_id);
        $this->assertEquals($invoice->id(), $lateFee->invoice_id);
    }

    public function testAddLateFeeToInvoiceNoLateFee(): void
    {
        $assessor = $this->getAssessor();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100]];
        $invoice->due_date = strtotime('-1 day');
        $invoice->saveOrFail();

        $this->assertFalse($assessor->addLateFeeToInvoice($invoice, self::$lateFeeSchedule));

        $this->assertCount(1, $invoice->items(true));
    }

    /**
     * @depends testApplyLateFees
     */
    public function testAddLateFeeToInvoiceExistingLateFee(): void
    {
        $assessor = $this->getAssessor();

        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100], ['unit_cost' => 50]];
        $invoice->due_date = strtotime('-10 days');
        $invoice->saveOrFail();
        self::mockLateFeeLegacy($invoice);

        $this->assertTrue($assessor->addLateFeeToInvoice($invoice, self::$lateFeeSchedule));

        $items = $invoice->items(true);
        $this->assertCount(3, $items);
        $this->assertEquals(7.5, $items[2]['amount']);

        $invoice->refresh();

        // running again should do nothing
        $this->assertTrue($assessor->addLateFeeToInvoice($invoice, self::$lateFeeSchedule));

        $items = $invoice->items(true);
        $this->assertCount(3, $items);
        $this->assertEquals(7.5, $items[2]['amount']);

        $this->assertEquals(1, LateFee::where('invoice_id', $invoice->id())->count());

        // changing due date should modify late fee amount due to recurring
        $invoice->due_date = strtotime('-30 days');
        $invoice->saveOrFail();

        $this->assertTrue($assessor->addLateFeeToInvoice($invoice, self::$lateFeeSchedule));

        $items = $invoice->items(true);
        $this->assertCount(3, $items);
        $this->assertEquals(23.65, $items[2]['amount']);

        $this->assertEquals(1, LateFee::where('invoice_id', $invoice->id())->count());
    }

    public function testAddLateFeeToInvoiceDeletedLateFee(): void
    {
        $assessor = $this->getAssessor();

        $items = self::$invoice->items();
        unset($items[1]);
        self::$invoice->items = $items;
        self::$invoice->saveOrFail();

        // when the late fee line item is deleted it should not be re-added
        $this->assertFalse($assessor->addLateFeeToInvoice(self::$invoice, self::$lateFeeSchedule));

        $this->assertCount(1, self::$invoice->items(true));
    }

    public function testAddLateFeeToInvoiceExistingLineItemMissingLateFee(): void
    {
        $invoice = new Invoice();
        $invoice->setCustomer(self::$customer);
        $invoice->items = [['unit_cost' => 100], ['type' => 'late_fee', 'name' => 'My late fee', 'unit_cost' => 123]];
        $invoice->due_date = strtotime('-10 days');
        $invoice->saveOrFail();

        $applier = new LateFeeApplierLegacy(self::getService('test.transaction_manager'), null, self::$lateFeeSchedule, $invoice);

        $this->assertTrue($applier->apply());

        // should replace the existing late fee line item
        $items = $invoice->items(true);
        $this->assertCount(2, $items);

        $expected = [
            'catalog_item' => null,
            'type' => 'late_fee',
            'name' => 'My late fee',
            'description' => null,
            'discountable' => true,
            'discounts' => [],
            'taxable' => true,
            'taxes' => [],
            'quantity' => 1.0,
            'unit_cost' => 5.0,
            'amount' => 5.0,
            'metadata' => new \stdClass(),
        ];
        unset($items[1]['id']);
        unset($items[1]['object']);
        unset($items[1]['created_at']);
        unset($items[1]['updated_at']);
        $this->assertEquals($expected, $items[1]);

        // should create a late fee object
        $lateFee = LateFee::where('invoice_id', $invoice->id())->oneOrNull();
        $this->assertInstanceOf(LateFee::class, $lateFee);
        $this->assertEquals(self::$customer->id(), $lateFee->customer_id);
        $this->assertEquals($invoice->id(), $lateFee->invoice_id);
    }
}
