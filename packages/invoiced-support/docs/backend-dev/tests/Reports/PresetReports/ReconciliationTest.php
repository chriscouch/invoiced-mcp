<?php

namespace App\Tests\Reports\PresetReports;

use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\AccountsReceivable\Operations\SetBadDebt;
use App\Reports\Interfaces\PresetReportInterface;
use App\Reports\Libs\PresetReportFactory;
use App\Reports\ValueObjects\FinancialReportGroup;
use App\Reports\ValueObjects\FinancialReportRow;
use App\Tests\AppTestCase;
use Carbon\CarbonImmutable;

class ReconciliationTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
    }

    public function testReconciliationReport(): void
    {
        $now = CarbonImmutable::now();
        $start = (new CarbonImmutable('-5 day'))->format('Y-m-d');
        $end = $now->format('Y-m-d');

        /** @var PresetReportFactory $factory */
        $factory = self::getService('test.preset_report_factory');

        $presetReport = $factory->get('reconciliation');

        $this->assertInstanceOf(PresetReportInterface::class, $presetReport);

        $parameters = [
            '$dateRange' => [
                'start' => $start,
                'end' => $end,
            ],
        ];

        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(1, $rows);
        $this->assertEquals(0, $rows[0][1]['value']);
        $this->assertEquals(0, $summary[1]['value']);
        $this->assertEquals(0, $netSummary[1]['value']);

        // test Legacy
        $this->createInvoice();
        self::$invoice->closed = true;
        self::$invoice->date_bad_debt = $now->unix();
        self::$invoice->amount_written_off = 100;
        self::$invoice->saveOrFail();

        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(2, $rows);
        $this->assertEquals('Invoices generated', $rows[0][0]);
        $this->assertEquals(100, $rows[0][1]['value']);
        $this->assertEquals('Invoices sent to bad debt', $rows[1][0]);
        $this->assertEquals(-100, $rows[1][1]['value']);
        $this->assertEquals(0, $netSummary[1]['value']);
        $this->assertEquals(0, $summary[1]['value']);

        // test half paid open
        $this->createInvoice();
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => self::$invoice->id,
                'amount' => 50,
            ],
        ];
        $payment->currency = 'usd';
        $payment->amount = 50;
        $payment->saveOrFail();

        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(3, $rows);
        $this->assertEquals('Invoices generated', $rows[0][0]);
        $this->assertEquals(200, $rows[0][1]['value']);
        $this->assertEquals('Payments - Other', $rows[1][0]);
        $this->assertEquals(-50, $rows[1][1]['value']);
        $this->assertEquals(-100, $rows[2][1]['value']);
        $this->assertEquals('Invoices sent to bad debt', $rows[2][0]);
        $this->assertEquals(50, $netSummary[1]['value']);
        $this->assertEquals(50, $summary[1]['value']);

        // test half paid closed
        self::$invoice->refresh();
        (new SetBadDebt())->set(self::$invoice);
        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(3, $rows);
        $this->assertEquals('Invoices generated', $rows[0][0]);
        $this->assertEquals(200, $rows[0][1]['value']);
        $this->assertEquals('Payments - Other', $rows[1][0]);
        $this->assertEquals(-50, $rows[1][1]['value']);
        $this->assertEquals('Invoices sent to bad debt', $rows[2][0]);
        $this->assertEquals(-150, $rows[2][1]['value']);
        $this->assertEquals(0, $netSummary[1]['value']);
        $this->assertEquals(0, $summary[1]['value']);

        // test latest behavior open
        $this->createInvoice();
        (new SetBadDebt())->set(self::$invoice);
        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(3, $rows);
        $this->assertEquals('Invoices generated', $rows[0][0]);
        $this->assertEquals(300, $rows[0][1]['value']);
        $this->assertEquals('Payments - Other', $rows[1][0]);
        $this->assertEquals(-50, $rows[1][1]['value']);
        $this->assertEquals('Invoices sent to bad debt', $rows[2][0]);
        $this->assertEquals(-250, $rows[2][1]['value']);
        $this->assertEquals(0, $netSummary[1]['value']);
        $this->assertEquals(0, $summary[1]['value']);

        // initial balance
        $this->createInvoice((new CarbonImmutable('-6 day'))->unix());
        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(3, $rows);
        $this->assertEquals('Invoices generated', $rows[0][0]);
        $this->assertEquals(300, $rows[0][1]['value']);
        $this->assertEquals('Payments - Other', $rows[1][0]);
        $this->assertEquals(-50, $rows[1][1]['value']);
        $this->assertEquals('Invoices sent to bad debt', $rows[2][0]);
        $this->assertEquals(-250, $rows[2][1]['value']);
        $this->assertEquals(0, $netSummary[1]['value']);
        // from here and below, initial balance is 100
        $this->assertEquals(100, $summary[1]['value']);

        // initial balance written off
        (new SetBadDebt())->set(self::$invoice);
        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(3, $rows);
        $this->assertEquals('Invoices generated', $rows[0][0]);
        $this->assertEquals(300, $rows[0][1]['value']);
        $this->assertEquals('Payments - Other', $rows[1][0]);
        $this->assertEquals(-50, $rows[1][1]['value']);
        $this->assertEquals('Invoices sent to bad debt', $rows[2][0]);
        $this->assertEquals(-350, $rows[2][1]['value']);
        $this->assertEquals(-100, $netSummary[1]['value']);
        $this->assertEquals(0, $summary[1]['value']);

        // void credit note
        $transaction = Transaction::where('invoice', self::$invoice->id())->one();
        $transaction->payment()?->void();
        $creditNote = $transaction->creditNote();
        $creditNote?->void();
        self::$invoice->refresh();

        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(5, $rows);
        $this->assertEquals('Invoices generated', $rows[0][0]);
        $this->assertEquals(300, $rows[0][1]['value']);
        $this->assertEquals('Payments - Other', $rows[1][0]);
        $this->assertEquals(-50, $rows[1][1]['value']);
        $this->assertEquals('Credit notes', $rows[2][0]);
        $this->assertEquals(-100, $rows[2][1]['value']);
        $this->assertEquals('Invoices sent to bad debt', $rows[3][0]);
        $this->assertEquals(-250, $rows[3][1]['value']);
        $this->assertEquals('Invoices and credit notes voided', $rows[4][0]);
        $this->assertEquals(100, $rows[4][1]['value']);
        $this->assertEquals(0, $netSummary[1]['value']);
        $this->assertEquals(100, $summary[1]['value']);

        // reopen and pay invoice
        self::hasInvoice();
        (new SetBadDebt())->set(self::$invoice);
        /** @var Transaction $transaction */
        $transaction = Transaction::where('invoice', self::$invoice->id)->one();
        $transaction->payment()?->void();
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->applied_to = [
            [
                'type' => 'invoice',
                'invoice' => self::$invoice->id,
                'amount' => 100,
            ],
        ];
        $payment->currency = 'usd';
        $payment->amount = 100;
        $payment->saveOrFail();
        $transaction->creditNote()?->void();

        [$rows, $netSummary, $summary] = $this->getRowsAndSummary($presetReport, $parameters);
        $this->assertCount(5, $rows);
        $this->assertEquals('Invoices generated', $rows[0][0]);
        $this->assertEquals(400, $rows[0][1]['value']);
        $this->assertEquals('Payments - Other', $rows[1][0]);
        $this->assertEquals(-150, $rows[1][1]['value']);
        $this->assertEquals('Credit notes', $rows[2][0]);
        $this->assertEquals(-200, $rows[2][1]['value']);
        $this->assertEquals('Invoices sent to bad debt', $rows[3][0]);
        $this->assertEquals(-250, $rows[3][1]['value']);
        $this->assertEquals('Invoices and credit notes voided', $rows[4][0]);
        $this->assertEquals(200, $rows[4][1]['value']);
        $this->assertEquals(0, $netSummary[1]['value']);
        $this->assertEquals(100, $summary[1]['value']);
    }

    private function createInvoice(?int $date = null): void
    {
        self::hasInvoice();
        self::$invoice->date = $date ?? (new CarbonImmutable('-3 day'))->unix();
        self::$invoice->saveOrFail();
    }

    private function getRowsAndSummary(PresetReportInterface $presetReport, array $parameters): array
    {
        $report = $presetReport->generate(self::$company, $parameters);
        $sections = $report->getSections();
        $groups = $sections[0]->getGroups();
        /** @var FinancialReportGroup $group */
        $group = $groups[0];
        $rows = $group->getRows();
        $row = $rows[0];
        $rows2 = $row->getRows();
        /** @var FinancialReportRow $row2 */
        $row2 = $rows2[1];

        return [
            $row2->getRows(),
            $row2->getSummary(),
            $row->getSummary(),
        ];
    }
}
