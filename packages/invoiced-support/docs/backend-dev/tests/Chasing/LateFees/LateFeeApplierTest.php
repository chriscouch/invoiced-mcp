<?php

namespace App\Tests\Chasing\LateFees;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\LateFees\LateFeeApplier;
use App\Chasing\Models\LateFee;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\Database\TransactionManager;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;
use Mockery;
use Mockery\MockInterface;

class LateFeeApplierTest extends AppTestCase
{
    private static MockInterface $transactionManager;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();

        self::$transactionManager = Mockery::mock(TransactionManager::class);
    }

    public function testZeroFee(): void
    {
        $schedule = $this->getSchedule();
        $invoice = $this->getInvoice();

        // one iteration
        $applier = $this->getApplier($schedule, null, $invoice);
        $this->assertTrue($applier->apply());

        // multiply iterations
        $invoice->due_date = time() - 186001;
        $schedule->recurring_days = 1;
        $applier = $this->getApplier($schedule, null, $invoice);
        $this->assertTrue($applier->apply());
    }

    public function testSuccess(): void
    {
        $schedule = $this->getSchedule(1);

        $invoice = $this->getInvoice();
        $applier = $this->getApplier($schedule, null, $invoice);
        self::$transactionManager->shouldReceive('perform')->once();
        $this->assertTrue($applier->apply());
    }

    public function testAlreadyApplied(): void
    {
        $schedule = $this->getSchedule();
        $invoice = $this->getInvoice();
        $fee = new LateFee();

        $applier = $this->getApplier($schedule, $fee, $invoice);
        $this->assertFalse($applier->apply());
    }

    public function testAlreadyAppliedRecurring(): void
    {
        $schedule = $this->getSchedule();
        $invoice = $this->getInvoice();
        $fee = new LateFee();
        $schedule->recurring_days = 1;

        // never
        $applier = $this->getApplier($schedule, $fee, $invoice);
        $this->assertFalse($applier->apply());

        // now
        $fee->date = CarbonImmutable::now();
        $applier = $this->getApplier($schedule, $fee, $invoice);
        $this->assertFalse($applier->apply());

        // in the past
        $fee->date = CarbonImmutable::now()->subDay();
        $applier = $this->getApplier($schedule, $fee, $invoice);
        $this->assertTrue($applier->apply());
    }

    public function testNoDueDate(): void
    {
        $schedule = $this->getSchedule();
        $invoice = $this->getInvoice();
        $invoice->due_date = null;

        $applier = $this->getApplier($schedule, null, $invoice);
        $this->assertFalse($applier->apply());
    }

    private function getSchedule(int $amount = 0): LateFeeSchedule
    {
        $schedule = new LateFeeSchedule();
        $schedule->recurring_days = 0;
        $schedule->amount = $amount;
        $schedule->is_percent = false;
        $schedule->grace_period = 0;

        return $schedule;
    }

    private function getInvoice(): Invoice
    {
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->balance = 1;
        $invoice->due_date = time() - 1;

        return $invoice;
    }

    private function getApplier(LateFeeSchedule $schedule, ?LateFee $fee, Invoice $invoice): LateFeeApplier
    {
        return new LateFeeApplier(
            self::$transactionManager,
            $fee,
            $schedule,
            $invoice,
        );
    }

    private function getSchedule2(int $fee = 2, int $interval = 0, bool $isPercent = true): LateFeeSchedule
    {
        $lateFeeSchedule = new LateFeeSchedule();
        $lateFeeSchedule->amount = $fee;
        $lateFeeSchedule->is_percent = $isPercent;
        $lateFeeSchedule->grace_period = 30;
        $lateFeeSchedule->recurring_days = $interval;

        return $lateFeeSchedule;
    }

    public function testCalculateAfterGracePercent(): void
    {
        $lateFeeSchedule = $this->getSchedule2();
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->due_date = strtotime('-31 days');
        $invoice->total = 200;
        $invoice->balance = 100;

        $calculator = new LateFeeApplier(self::getService('test.transaction_manager'), null, $lateFeeSchedule, $invoice);

        $fee = $calculator->calculate();

        $this->assertInstanceOf(Money::class, $fee);
        $this->assertEquals(200, $fee->amount);
        $this->assertEquals('usd', $fee->currency);
    }

    public function testCalculateAfterGraceFlat(): void
    {
        $lateFeeSchedule = $this->getSchedule2(
            fee: 5,
            isPercent: false
        );
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->due_date = strtotime('-31 days');
        $invoice->total = 205;
        $invoice->balance = 105;

        $calculator = new LateFeeApplier(self::getService('test.transaction_manager'), null, $lateFeeSchedule, $invoice);

        $fee = $calculator->calculate();

        $this->assertInstanceOf(Money::class, $fee);
        $this->assertEquals(500, $fee->amount);
        $this->assertEquals('usd', $fee->currency);
    }
}
