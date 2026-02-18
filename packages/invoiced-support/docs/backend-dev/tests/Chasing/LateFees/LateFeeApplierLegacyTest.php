<?php

namespace App\Tests\Chasing\LateFees;

use App\AccountsReceivable\Models\Invoice;
use App\Chasing\LateFees\LateFeeApplierLegacy;
use App\Chasing\Models\LateFeeSchedule;
use App\Core\I18n\ValueObjects\Money;
use App\Tests\AppTestCase;

class LateFeeApplierLegacyTest extends AppTestCase
{
    private function getSchedule(int $fee = 2, int $interval = 0, bool $isPercent = true): LateFeeSchedule
    {
        $lateFeeSchedule = new LateFeeSchedule();
        $lateFeeSchedule->amount = $fee;
        $lateFeeSchedule->is_percent = $isPercent;
        $lateFeeSchedule->grace_period = 30;
        $lateFeeSchedule->recurring_days = $interval;

        return $lateFeeSchedule;
    }

    public function testCalculateNotLate(): void
    {
        $lateFeeSchedule = $this->getSchedule();

        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->due_date = strtotime('-30 days');
        $invoice->total = 200;
        $invoice->balance = 100;

        $calculator = new LateFeeApplierLegacy(self::getService('test.transaction_manager'), null, $lateFeeSchedule, $invoice);

        $fee = $calculator->calculate();

        $this->assertInstanceOf(Money::class, $fee);
        $this->assertEquals(0, $fee->amount);
        $this->assertEquals('usd', $fee->currency);
    }

    public function testCalculateAfterGracePercent(): void
    {
        $lateFeeSchedule = $this->getSchedule();
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->due_date = strtotime('-31 days');
        $invoice->total = 200;
        $invoice->balance = 100;

        $calculator = new LateFeeApplierLegacy(self::getService('test.transaction_manager'), null, $lateFeeSchedule, $invoice);

        $fee = $calculator->calculate();

        $this->assertInstanceOf(Money::class, $fee);
        $this->assertEquals(200, $fee->amount);
        $this->assertEquals('usd', $fee->currency);
    }

    public function testCalculateAfterGraceFlat(): void
    {
        $lateFeeSchedule = $this->getSchedule(
            fee: 5,
            isPercent: false
        );
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->due_date = strtotime('-31 days');
        $invoice->total = 205;
        $invoice->balance = 105;

        $calculator = new LateFeeApplierLegacy(self::getService('test.transaction_manager'), null, $lateFeeSchedule, $invoice);

        $fee = $calculator->calculate();

        $this->assertInstanceOf(Money::class, $fee);
        $this->assertEquals(500, $fee->amount);
        $this->assertEquals('usd', $fee->currency);
    }

    public function testCalculateRecurringPercent(): void
    {
        $lateFeeSchedule = $this->getSchedule(2, 5);
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->due_date = strtotime('-45 days');
        $invoice->total = 200;
        $invoice->balance = 100;

        $calculator = new LateFeeApplierLegacy(self::getService('test.transaction_manager'), null, $lateFeeSchedule, $invoice);

        $fee = $calculator->calculate();

        // the fee should be $100 * (1 + .02) ^ 4 - $100
        $this->assertInstanceOf(Money::class, $fee);
        $this->assertEquals(824, $fee->amount);
        $this->assertEquals('usd', $fee->currency);
    }

    public function testCalculateRecurringFlat(): void
    {
        $lateFeeSchedule = $this->getSchedule(2, 5, false);
        $invoice = new Invoice();
        $invoice->currency = 'usd';
        $invoice->due_date = strtotime('-47 days');
        $invoice->total = 300;
        $invoice->balance = 200;

        $calculator = new LateFeeApplierLegacy(self::getService('test.transaction_manager'), null, $lateFeeSchedule, $invoice);

        $fee = $calculator->calculate();

        // the fee should be $2 * 4
        $this->assertInstanceOf(Money::class, $fee);
        $this->assertEquals(800, $fee->amount);
        $this->assertEquals('usd', $fee->currency);
    }
}
